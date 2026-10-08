<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Client;
use App\Support\Export;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cadastro de clientes da empresa aberta na sessão. A consulta, o filtro e a
 * página vêm do MySQL desta empresa — o escopo global de tenant garante isso, e
 * nenhuma lista ou exportação atravessa para a empresa ao lado.
 */
class ClientController extends Controller
{
    private const ORDENAVEIS = ['name', 'trade_name', 'document', 'status', 'created_at'];

    private const FILTROS = ['busca', 'situacao', 'cidade'];

    public function index(Request $request): View
    {
        return view('clients.index', [
            'clientes' => $this->consulta($request)
                ->with('addresses')
                ->withCount(['contacts', 'serviceOrders'])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'name'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'cidades' => $this->cidades(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
            'situacoes' => StatusCatalog::options('client') + ['excluidos' => 'Excluídos temporariamente'],
        ]);
    }

    public function create(): View
    {
        return view('clients.form', [
            'cliente' => new Client(['status' => 'active']),
            'situacoes' => StatusCatalog::options('client'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cliente = Client::create($request->validate($this->regras(), $this->mensagens()));

        return redirect()
            ->route('clients.show', $cliente)
            ->with('status', "Cliente “{$cliente->name}” cadastrado.");
    }

    public function show(Request $request, Client $cliente): View
    {
        $cliente->load(['contacts', 'addresses']);

        return view('clients.show', [
            'cliente' => $cliente,
            'historico' => $this->historico($cliente),
            'situacoes' => StatusCatalog::options('client'),
            'tiposDeEndereco' => StatusCatalog::options('address'),
            'contatoEmEdicao' => $this->emEdicao($request, 'editar_contato', $cliente->contacts),
            'enderecoEmEdicao' => $this->emEdicao($request, 'editar_endereco', $cliente->addresses),
        ]);
    }

    public function edit(Client $cliente): View
    {
        return view('clients.form', [
            'cliente' => $cliente,
            'situacoes' => StatusCatalog::options('client'),
        ]);
    }

    public function update(Request $request, Client $cliente): RedirectResponse
    {
        $cliente->update($request->validate($this->regras($cliente), $this->mensagens()));

        return redirect()
            ->route('clients.show', $cliente)
            ->with('status', "Cliente “{$cliente->name}” atualizado.");
    }

    /**
     * Excluir um cliente que já gerou trabalho apagaria o rastro de uma operação
     * real. A saída honesta é inativar: some da carteira e o histórico continua
     * legível. Por isso a exclusão é recusada enquanto houver registro vinculado.
     */
    public function destroy(Client $cliente): RedirectResponse
    {
        $historico = $this->historico($cliente);

        if (array_sum(array_column($historico, 'total')) > 0) {
            $vinculos = collect($historico)
                ->filter(fn (array $linha) => $linha['total'] > 0)
                ->map(fn (array $linha) => $linha['total'].' '.($linha['total'] === 1 ? $linha['singular'] : $linha['plural']))
                ->implode(', ');

            return back()->with('erro', sprintf(
                '%s tem histórico registrado (%s). Para tirá-lo da operação sem perder nada, mude a situação para Inativo.',
                $cliente->name,
                $vinculos,
            ));
        }

        $nome = $cliente->name;
        $cliente->delete();

        return redirect()
            ->route('clients.index')
            ->with('status', "Cliente “{$nome}” excluído.");
    }

    public function restore(int $cliente): RedirectResponse
    {
        $registro = Client::withTrashed()->findOrFail($cliente);
        $registro->restore();

        return redirect()
            ->route('clients.show', $registro)
            ->with('status', "Cliente “{$registro->name}” restaurado.");
    }

    public function export(Request $request): StreamedResponse
    {
        return Export::csv(
            'clientes',
            ['Nome', 'Nome fantasia', 'Documento', 'E-mail', 'Telefone', 'Situação', 'Cidade', 'UF', 'Contatos', 'Ordens de serviço', 'Cadastrado em'],
            $this->consulta($request)
                ->with('addresses')
                ->withCount(['contacts', 'serviceOrders'])
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'name'))
                ->lazyById(200)
                ->map($this->linhaCsv(...)),
        );
    }

    /** @return array<int, string> */
    private function linhaCsv(Client $cliente): array
    {
        $endereco = $cliente->enderecoPrincipal();

        return [
            $cliente->name,
            $cliente->trade_name,
            $cliente->document,
            $cliente->email,
            $cliente->phone,
            StatusCatalog::label('client', $cliente->status),
            $endereco?->city,
            $endereco?->state,
            $cliente->contacts_count,
            $cliente->service_orders_count,
            $cliente->created_at,
        ];
    }

    private function consulta(Request $request): Builder
    {
        $situacao = trim((string) $request->query('situacao'));
        $cidade = trim((string) $request->query('cidade'));

        $query = $situacao === 'excluidos'
            ? Client::query()->withTrashed()->onlyTrashed()
            : ListFilters::igual(Client::query(), $request, 'situacao', 'status', ['active', 'inactive']);

        $query = ListFilters::busca($query, $request, ['name', 'trade_name', 'document', 'email', 'phone']);

        return $cidade === ''
            ? $query
            : $query->whereHas('addresses', fn (Builder $endereco) => $endereco->where('city', $cidade));
    }

    /**
     * Qual contato/endereço a ficha deve abrir em modo de edição. A resposta vem do
     * `?editar_*=id`, e a peça é procurada dentro da coleção que já está carregada
     * desta empresa: um id de outra empresa não existe nesta coleção, então a tela
     * simplesmente volta ao formulário de inclusão em vez de vazar registro alheio.
     *
     * @param  Collection<int, Model>  $pecas
     */
    private function emEdicao(Request $request, string $param, Collection $pecas): ?Model
    {
        $id = $request->query($param);

        return ctype_digit((string) $id) ? $pecas->firstWhere('id', (int) $id) : null;
    }

    /** @return array<int, array{singular: string, plural: string, total: int}> */
    private function historico(Client $cliente): array
    {
        return [
            [
                'singular' => 'ordem de serviço',
                'plural' => 'ordens de serviço',
                'total' => $cliente->serviceOrders()->count(),
            ],
            [
                'singular' => 'chamado',
                'plural' => 'chamados',
                'total' => $cliente->tickets()->count(),
            ],
            [
                'singular' => 'compromisso na agenda',
                'plural' => 'compromissos na agenda',
                'total' => $cliente->appointments()->count(),
            ],
            [
                'singular' => 'lançamento financeiro',
                'plural' => 'lançamentos financeiros',
                'total' => $cliente->financialRecords()->count(),
            ],
        ];
    }

    /** Cidades que realmente aparecem nos endereços desta empresa. */
    private function cidades(): array
    {
        return Address::query()
            ->whereNotNull('city')
            ->where('city', '!=', '')
            ->distinct()
            ->orderBy('city')
            ->pluck('city', 'city')
            ->all();
    }

    /** @return array<string, mixed> */
    private function regras(?Client $cliente = null): array
    {
        $empresa = TenantContext::id();

        return [
            'name' => ['required', 'string', 'max:180'],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'document' => [
                'nullable',
                'string',
                'max:25',
                Rule::unique('clients', 'document')
                    // O verificador de unicidade entrega o consulta base, não o Builder do Eloquent.
                    ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at'))
                    ->ignore($cliente?->id),
            ],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(array_keys(StatusCatalog::options('client')))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * O documento vale apenas dentro da empresa, então a mensagem precisa dizer
     * isso: "já existe" solto faria o usuário procurar um cadastro que não é dele.
     *
     * @return array<string, string>
     */
    private function mensagens(): array
    {
        return [
            'document.unique' => 'Este documento já está cadastrado nesta empresa.',
        ];
    }
}
