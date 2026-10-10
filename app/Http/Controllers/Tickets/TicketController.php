<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tickets\Concerns\EnxergaOChamado;
use App\Models\Client;
use App\Models\ServiceCategory;
use App\Models\ServiceOrder;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Recusa;
use App\Services\Tickets\FluxoDeChamado;
use App\Support\Export;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use App\Support\TextoSeguro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chamados da empresa. O chamado é a voz do cliente registrada: quem ligou, o que
 * aconteceu com o equipamento, o que o escritório respondeu. Diferente da ordem,
 * ele não cobra nada e não tem linha — o que ele tem é prazo, conversa e um estado
 * que fecha a conversa, não uma conta.
 *
 * O alcance é o mesmo das ordens: o técnico recebe os dele, a conta de cliente
 * recebe a carteira dela, e o escritório recebe a operação. A nota interna só não
 * chega ao HTML de quem não tem `tickets.update` porque a consulta já a deixa de
 * fora.
 *
 * A escrita é do degrau abaixo: `FluxoDeChamado` abre o protocolo com a passagem de
 * origem, conduz o estado com carimbo e sino, e é dele a régua que decide se o passo
 * é deste autor. Aqui fica o que é de apresentação — quem enxerga o quê, qual campo
 * o formulário oferece e como a resposta vira frase na tela.
 */
class TicketController extends Controller
{
    use EnxergaOChamado;

    private const ORDENAVEIS = [
        'protocol', 'subject', 'priority', 'status', 'opened_at', 'resolved_at', 'closed_at', 'created_at',
    ];

    private const FILTROS = ['busca', 'situacao', 'prioridade', 'categoria', 'tecnico', 'cliente', 'inicio', 'fim', 'atrasado'];

    public function __construct(private readonly FluxoDeChamado $fluxo) {}

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $restrito = Ticket::alcanceRestrito($usuario);
        $ordem = ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'opened_at', 'desc');

        return view('tickets.index', [
            'chamados' => $this->consulta($request, $usuario)
                ->with(['client:id,name', 'technician:id,name', 'serviceOrder:id,number'])
                ->withCount('comments')
                ->orderBy(...$ordem)
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'categorias' => $this->categorias(),
            'situacoes' => StatusCatalog::options('ticket'),
            'prioridades' => StatusCatalog::options('priority'),
            'tecnicos' => $restrito ? [] : $this->tecnicos(),
            'clientes' => $restrito ? [] : $this->clientes(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
            'restrito' => $restrito,
        ]);
    }

    public function create(Request $request): View
    {
        $usuario = $request->user();
        $chamado = new Ticket(['priority' => 'normal']);
        $contaCliente = $usuario->client_id !== null;

        // A conta de cliente não escolhe para quem abre: o chamado é dela, e o
        // servidor escreve a carteira dela seja o que vier no formulário.
        $cliente = $contaCliente
            ? Client::query()->find($usuario->client_id)
            : $this->clientePreferido($request);

        if ($cliente !== null) {
            $chamado->client_id = $cliente->id;
            $chamado->setRelation('client', $cliente);
        }

        if ($request->filled('ordem')) {
            $origem = $this->ordens($usuario)->whereKey($request->integer('ordem'))->first();

            if ($origem !== null) {
                $chamado->service_order_id = $origem->id;
                $chamado->category = $origem->service?->category?->slug;
            }
        }

        return view('tickets.form', [
            'chamado' => $chamado,
            'clientes' => $contaCliente ? [] : $this->clientes(),
            'categorias' => $this->categorias(),
            'prioridades' => StatusCatalog::options('priority'),
            'tecnicos' => $contaCliente ? [] : $this->tecnicos(),
            'responsaveis' => $contaCliente ? [] : $this->responsaveis(),
            'ordens' => $contaCliente ? [] : $this->ordensParaFicha($usuario),
            'contaCliente' => $contaCliente,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $dados = $request->validate($this->regras($usuario), $this->mensagens());

        $chamado = $this->fluxo->abrir($usuario, $dados);

        return redirect()
            ->route('tickets.show', $chamado)
            ->with('status', "Chamado {$chamado->protocol} aberto para {$chamado->client->name}.");
    }

    public function show(Request $request, Ticket $chamado): View
    {
        $usuario = $request->user();
        $this->garantirVisivel($chamado, $usuario);

        $chamado->load(['client', 'serviceOrder.service', 'technician', 'responsibleUser', 'statusHistory.user']);

        // A nota interna não sai do banco para quem não tem `tickets.update`: não é
        // CSS escondendo a linha, é a consulta que não a traz.
        $notas = $chamado->comments()
            ->when(! $usuario->hasPermission('tickets.update'), fn (Builder $q) => $q->where('is_internal', false))
            ->with(['user:id,name', 'client:id,name'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return view('tickets.show', [
            'chamado' => $chamado,
            'notas' => $notas,
            'proximosEstados' => $this->fluxo->proximosEstados($chamado, $usuario),
            'categoria' => $this->categorias()[$chamado->category] ?? null,
            'podeMover' => $usuario->hasPermission('tickets.execute'),
            'podeInterna' => $usuario->hasPermission('tickets.update'),
            'podeFechar' => $usuario->hasPermission('tickets.close'),
        ]);
    }

    public function edit(Request $request, Ticket $chamado): View
    {
        $usuario = $request->user();
        $this->garantirVisivel($chamado, $usuario);

        $contaCliente = $usuario->client_id !== null;
        $chamado->loadMissing('client');

        return view('tickets.form', [
            'chamado' => $chamado,
            'clientes' => $contaCliente ? [] : $this->clientes(),
            'categorias' => $this->categorias(),
            'prioridades' => StatusCatalog::options('priority'),
            'tecnicos' => $contaCliente ? [] : $this->tecnicos(),
            'responsaveis' => $contaCliente ? [] : $this->responsaveis(),
            'ordens' => $contaCliente ? [] : $this->ordensParaFicha($usuario),
            'contaCliente' => $contaCliente,
        ]);
    }

    public function update(Request $request, Ticket $chamado): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($chamado, $usuario);

        $dados = $request->validate($this->regras($usuario, $chamado), $this->mensagens());

        $this->fluxo->atualizar($chamado, $dados, $usuario);

        return redirect()
            ->route('tickets.show', $chamado)
            ->with('status', "Chamado {$chamado->protocol} atualizado.");
    }

    /**
     * O único caminho que muda o estado do chamado, e ele passa pelo serviço: além da
     * coluna, o passo põe o carimbo do momento e grava a passagem com quem fez — é o
     * que responde "quem atendeu e quando" três meses depois, e é o que a ficha desenha
     * como linha do tempo.
     */
    public function mudarStatus(Request $request, Ticket $chamado): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($chamado, $usuario);

        $destino = (string) $request->input('estado');
        $exigeNota = $this->fluxo->notaObrigatoria($chamado, $destino);

        $validado = $request->validate([
            'estado' => ['required', Rule::in(array_keys(StatusCatalog::options('ticket')))],
            // `required` e não uma regra sobre o valor: campo ausente da requisição
            // não passa por regra de closure, e forjar o passo sem a linha `nota`
            // resolveria um chamado sem dizer o que foi feito.
            'nota' => [$exigeNota ? 'required' : 'nullable', 'string', 'max:1000'],
        ], $exigeNota ? ['nota.required' => $this->fluxo->mensagemDaNota($destino)] : []);

        try {
            $origem = $this->fluxo->mudarStatus($chamado, $usuario, $destino, $validado['nota'] ?? null);
        } catch (Recusa $recusa) {
            return back()->with($recusa->tom(), $recusa->getMessage());
        }

        return back()->with('status', sprintf(
            'Chamado %s: %s → %s.',
            $chamado->protocol,
            StatusCatalog::label('ticket', $origem),
            StatusCatalog::label('ticket', $destino),
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $usuario = $request->user();
        $categorias = $this->categorias();
        $ordem = ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'opened_at', 'desc');

        return Export::csv(
            'chamados',
            ['Protocolo', 'Assunto', 'Cliente', 'Categoria', 'Ordem', 'Técnico', 'Responsável', 'Estado',
                'Prioridade', 'Aberto em', 'Prazo', 'Prazo vencido', 'Resolvido em', 'Fechado em',
                'Resolução', 'Descrição'],
            $this->consulta($request, $usuario)
                ->with(['client:id,name', 'technician:id,name', 'responsibleUser:id,name', 'serviceOrder:id,number'])
                ->orderBy(...$ordem)
                ->lazyById(200)
                ->map(fn (Ticket $chamado) => $this->linhaCsv($chamado, $categorias)),
        );
    }

    /**
     * A consulta da listagem, da exportação e do filtro de atrasados. O alcance entra
     * antes dos filtros: um técnico que digitar `?cliente=99` na URL não chega à
     * linha de outro técnico.
     */
    private function consulta(Request $request, User $usuario): Builder
    {
        $query = Ticket::query()->visiveisPara($usuario);

        $query = ListFilters::igual($query, $request, 'situacao', 'status', array_keys(StatusCatalog::options('ticket')));
        $query = ListFilters::igual($query, $request, 'prioridade', 'priority', array_keys(StatusCatalog::options('priority')));
        $query = ListFilters::igual($query, $request, 'categoria', 'category', array_keys($this->categorias()));
        $query = ListFilters::busca($query, $request, ['protocol', 'subject', 'description']);
        $query = ListFilters::relacionado($query, $request, 'cliente', 'client');
        $query = ListFilters::relacionado($query, $request, 'tecnico', 'technician');

        if (filled($request->query('atrasado'))) {
            $query = $query->atrasados();
        }

        return ListFilters::periodo($query, $request, 'opened_at');
    }

    /** @return array<int, mixed> */
    private function linhaCsv(Ticket $chamado, array $categorias): array
    {
        $prazo = $chamado->prazoResolucao();

        return [
            $chamado->protocol,
            $chamado->subject,
            $chamado->client->name,
            $categorias[$chamado->category] ?? $chamado->category,
            $chamado->serviceOrder?->number,
            $chamado->technician?->name,
            $chamado->responsibleUser?->name,
            StatusCatalog::label('ticket', $chamado->status),
            StatusCatalog::label('priority', $chamado->priority),
            $chamado->opened_at,
            $prazo,
            $chamado->prazoVencido() ? 'sim' : 'não',
            $chamado->resolved_at,
            $chamado->closed_at,
            TextoSeguro::textoPlano($chamado->resolution_note),
            TextoSeguro::textoPlano($chamado->description),
        ];
    }

    /** @return array<string, string> slug => nome, lido das categorias de serviço da empresa */
    private function categorias(): array
    {
        return ServiceCategory::query()->orderBy('name')->pluck('name', 'slug')->all();
    }

    /** @return array<int, string> */
    private function clientes(): array
    {
        return Client::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    private function tecnicos(): array
    {
        return Technician::query()->where('status', '!=', 'inactive')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    private function responsaveis(): array
    {
        return User::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** Ordens que este usuário alcança: é a elas que um chamado pode estar preso. */
    private function ordens(User $usuario): Builder
    {
        return ServiceOrder::query()->visiveisPara($usuario);
    }

    /**
     * As ordens alcançáveis já como rótulo de select: o número é o que o escritório
     * fala ao telefone, o título é o que ele lembra na hora.
     *
     * @return array<int, string>
     */
    private function ordensParaFicha(User $usuario): array
    {
        return $this->ordens($usuario)
            ->latest('created_at')
            ->get()
            ->mapWithKeys(fn (ServiceOrder $ordem) => [$ordem->id => $ordem->number.' · '.$ordem->title])
            ->all();
    }

    /** O cliente que a tela de origem apontou em `?cliente=`; nada além dele. */
    private function clientePreferido(Request $request): ?Client
    {
        $id = $request->query('cliente');

        return ctype_digit((string) $id) ? Client::query()->find((int) $id) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function regras(User $usuario, ?Ticket $chamado = null): array
    {
        $empresa = TenantContext::id();

        return [
            // Para a conta de cliente a carteira vem da sessão, não do formulário.
            'client_id' => [$usuario->client_id === null ? 'required' : 'nullable', Rule::exists('clients', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at'))],
            'service_order_id' => ['nullable', Rule::exists('service_orders', 'id')
                ->where(function ($query) use ($empresa, $usuario): void {
                    // Não basta ser da empresa: prender um chamado à ordem de outro
                    // cliente revelaria o número dela na ficha.
                    $query->where('company_id', $empresa)
                        ->whereIn('id', ServiceOrder::query()->visiveisPara($usuario)->select('id'));
                })],
            'technician_id' => ['nullable', Rule::exists('technicians', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa)
                    ->where('status', '<>', 'inactive')->whereNull('deleted_at'))],
            'responsible_user_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $empresa)],
            'subject' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:20000'],
            'category' => ['required', Rule::in(array_keys($this->categorias()))],
            'priority' => ['required', Rule::in(array_keys(StatusCatalog::options('priority')))],
        ];
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'client_id.exists' => 'O cliente precisa ser um cadastro da sua empresa.',
            'service_order_id.exists' => 'A ordem precisa ser uma ordem sua ou da sua empresa.',
            'technician_id.exists' => 'O técnico precisa estar na sua escala.',
            'responsible_user_id.exists' => 'O responsável precisa ser um usuário da sua empresa.',
            'category.in' => 'A categoria precisa ser uma categoria de serviço cadastrada.',
        ];
    }
}
