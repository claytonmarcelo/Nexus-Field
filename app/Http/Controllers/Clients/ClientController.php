<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Concerns\EmEdicao;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\FinancialRecord;
use App\Models\ServiceOrder;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Export;
use App\Support\FichaHistorico;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cadastro de clientes da empresa aberta na sessão. A consulta, o filtro e a
 * página vêm do MySQL desta empresa — o escopo global de tenant garante isso, e
 * nenhuma lista ou exportação atravessa para a empresa ao lado.
 *
 * A ficha não é só o cadastro: ela devolve o trabalho que aquele cliente já
 * gerou, lido do banco na hora e pelo alcance de quem abriu a tela. É por isso
 * que cada número mora no cartão do domínio dele — nenhum cartão de contagens
 * soltas, nenhum valor digitado na tela.
 */
class ClientController extends Controller
{
    use EmEdicao;

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

    /**
     * A ficha: o cadastro, os contatos, os endereços e o que ele já gerou —
     * ordens, chamados, as janelas que ainda vêm no calendário, os serviços que
     * saíram das ordens dele, o caixa no nome dele e a trilha da própria linha.
     *
     * O alcance entra antes de tudo: um técnico com `clients.view` aberto nesta
     * ficha vê as ordens e os chamados dele próprio neste cliente, não a carteira
     * inteira da empresa. Os dois cartões de gestão (financeiro e trilha) só
     * existem para quem tem o degrau de leitura deles, e por isso o controller
     * devolve `null` quando a conta não tem — a tela não decide sozinha o que o
     * banco já decidiu.
     */
    public function show(Request $request, Client $cliente): View
    {
        $cliente->load(['contacts', 'addresses']);
        $usuario = $request->user();

        $ordens = ServiceOrder::query()->visiveisPara($usuario)->where('client_id', $cliente->id);
        $chamados = Ticket::query()->visiveisPara($usuario)->where('client_id', $cliente->id);

        return view('clients.show', [
            'cliente' => $cliente,
            'totalOrdens' => (clone $ordens)->count(),
            'ordens' => (clone $ordens)
                ->with(['technician:id,name', 'service:id,name'])
                // A mesma régua de ordenação da listagem: a ficha mostra o que a
                // lista mostraria por cima.
                ->orderByDesc('scheduled_starts_at')
                ->limit(FichaHistorico::REGISTROS_NA_FICHA)
                ->get(),
            'totalChamados' => (clone $chamados)->count(),
            'chamados' => (clone $chamados)
                ->with(['technician:id,name'])
                ->latest('opened_at')
                ->limit(FichaHistorico::REGISTROS_NA_FICHA)
                ->get(),
            'agenda' => FichaHistorico::janelas(
                Appointment::query()->visiveisPara($usuario)->where('client_id', $cliente->id),
            ),
            'servicos' => $this->servicosUsados($cliente, $usuario),
            'financas' => $usuario->hasPermission('financial.view') ? $this->financas($cliente) : null,
            'trilha' => $usuario->hasPermission('audit.view') ? FichaHistorico::trilha($cliente) : null,
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
     * A regra de exclusão, contada no banco. A ficha não expõe esta soma num
     * cartão próprio: cada um destes números vive no cartão do domínio que o
     * mostra, e a frase de recusa continua dizendo tudo — a informação existe uma
     * vez, não duas.
     *
     * @return array<int, array{singular: string, plural: string, total: int}>
     */
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

    /**
     * Os serviços que saíram das ordens deste cliente, somados no MySQL: em
     * quantas ordens entraram, quantas unidades e quanto isso já virou de conta.
     * Não existe cadastro de "serviços do cliente" — o que ele usa é o que as
     * linhas das ordens dele cobram, e é de lá que o número vem.
     *
     * `DB::table` não passa pelo escopo global de tenant, então o recorte vem dos
     * ids das ordens que aquele usuário enxerga neste cliente: a mesma consulta
     * que a listagem de ordens faria. Linha de produto (sem `service_id`) fica
     * fora por construção do `join`, e um serviço retirado do catálogo depois
     * continua na conta que ele já gerou.
     *
     * @return Collection<int, object>
     */
    private function servicosUsados(Client $cliente, User $usuario): Collection
    {
        $ordens = ServiceOrder::query()
            ->visiveisPara($usuario)
            ->where('client_id', $cliente->id)
            ->select('id');

        return DB::table('service_order_items')
            ->join('services', 'services.id', '=', 'service_order_items.service_id')
            ->whereIn('service_order_items.service_order_id', $ordens)
            ->selectRaw('services.name as servico, '
                .'count(distinct service_order_items.service_order_id) as ordens, '
                .'sum(service_order_items.quantity) as quantidade, '
                .'sum(service_order_items.quantity * service_order_items.unit_price - service_order_items.discount) as valor')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('valor')
            ->limit(FichaHistorico::REGISTROS_NA_FICHA)
            ->get();
    }

    /**
     * O caixa no nome deste cliente, somado pela mesma expressão da listagem
     * financeira (`FinancialRecord::totais`): a ficha não tem régua própria, e o
     * que ela mostra é o que a lista mostra filtrada nele.
     *
     * Receita, porque é o que ele deve; despesa da empresa por causa dele não é
     * dívida dele, e misturar os dois num cartão intitulado "o que ele deve"
     * seria a conta errada com o número certo.
     *
     * @return array{registros: int, bruto: float, pago: float, em_aberto: float, vencido: int, vencido_valor: float}
     */
    private function financas(Client $cliente): array
    {
        return FinancialRecord::totais(
            FinancialRecord::query()->where('client_id', $cliente->id)->revenue(),
        );
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
