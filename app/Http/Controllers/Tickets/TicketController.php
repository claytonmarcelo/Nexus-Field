<?php

namespace App\Http\Controllers\Tickets;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tickets\Concerns\EnxergaOChamado;
use App\Models\Client;
use App\Models\ServiceCategory;
use App\Models\ServiceOrder;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\TicketStatusHistory;
use App\Models\User;
use App\Support\Export;
use App\Support\ListFilters;
use App\Support\Notifier;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use App\Support\TextoSeguro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
 */
class TicketController extends Controller
{
    use EnxergaOChamado;

    private const ORDENAVEIS = [
        'protocol', 'subject', 'priority', 'status', 'opened_at', 'resolved_at', 'closed_at', 'created_at',
    ];

    private const FILTROS = ['busca', 'situacao', 'prioridade', 'categoria', 'tecnico', 'cliente', 'inicio', 'fim', 'atrasado'];

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

        $chamado = DB::transaction(function () use ($dados, $usuario): Ticket {
            $chamado = Ticket::create([
                ...$this->normaliza($dados, $usuario),
                'protocol' => Ticket::proximoProtocolo(),
                'status' => 'open',
                'opened_at' => now(),
            ]);

            TicketStatusHistory::query()->create([
                'ticket_id' => $chamado->id,
                'user_id' => $usuario->id,
                'from_status' => null,
                'to_status' => $chamado->status,
                'note' => 'Chamado aberto na tela de chamados.',
                'created_at' => now(),
            ]);

            return $chamado;
        });

        $this->avisarChamadoAberto($chamado, $usuario);

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
            'proximosEstados' => $this->proximosEstados($chamado, $usuario),
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

        $chamado->update($this->normaliza($dados, $usuario));

        return redirect()
            ->route('tickets.show', $chamado)
            ->with('status', "Chamado {$chamado->protocol} atualizado.");
    }

    /**
     * O único caminho que muda o estado do chamado. Além da coluna, põe o carimbo
     * do momento e grava a passagem com quem fez: é o que responde "quem atendeu e
     * quando" três meses depois, e é o que a ficha desenha como linha do tempo.
     */
    public function mudarStatus(Request $request, Ticket $chamado): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($chamado, $usuario);

        $destino = (string) $request->input('estado');
        $exigeNota = $this->notaObrigatoria($chamado, $destino);

        $validado = $request->validate([
            'estado' => ['required', Rule::in(array_keys(StatusCatalog::options('ticket')))],
            // `required` e não uma regra sobre o valor: campo ausente da requisição
            // não passa por regra de closure, e forjar o passo sem a linha `nota`
            // resolveria um chamado sem dizer o que foi feito.
            'nota' => [$exigeNota ? 'required' : 'nullable', 'string', 'max:1000'],
        ], $exigeNota ? ['nota.required' => $this->mensagemDaNota($destino)] : []);

        if (! $chamado->podeMudarPara($destino)) {
            return back()->with('erro', sprintf(
                'O chamado %s está “%s” e não pode ir para “%s”: o fluxo do módulo é o que vale.',
                $chamado->protocol,
                StatusCatalog::label('ticket', $chamado->status),
                StatusCatalog::label('ticket', $destino),
            ));
        }

        if (! array_key_exists($destino, $this->proximosEstados($chamado, $usuario))) {
            return back()->with('erro', 'Resolver e fechar um chamado pedem a permissão de encerramento, que esta conta não tem.');
        }

        $origem = StatusCatalog::label('ticket', $chamado->status);
        $chamado->mudarStatus($destino, $usuario, $validado['nota'] ?? null);

        if ($destino === 'resolved') {
            $this->avisarResolucao($chamado, $usuario, $validado['nota'] ?? null);
        }

        return back()->with('status', sprintf(
            'Chamado %s: %s → %s.',
            $chamado->protocol,
            $origem,
            StatusCatalog::label('ticket', $destino),
        ));
    }

    /**
     * Chamado novo toca para quem pode atendê-lo: toda conta ativa da empresa
     * com a permissão `tickets.execute`. Um sino por chamado — a mesma
     * permissão não dobra o aviso, e quem abriu o chamado não ouve o ato que
     * acabou de praticar.
     */
    private function avisarChamadoAberto(Ticket $chamado, User $usuario): void
    {
        $título = sprintf('Chamado %s aberto: %s', $chamado->protocol, $chamado->subject);
        $link = route('tickets.show', $chamado);

        Notifier::paraQuemPode('tickets.execute', 'chamdo.aberto', $título, null, $link, [
            'ticket_id' => $chamado->id,
        ], $usuario);
    }

    /**
     * Resolver é o único meio-de-percurso que toca sino: a conta de cliente dona
     * do chamado e o responsável apontado. A nota da resolução viaja como corpo
     * do aviso porque o que o outro lado espera é a resposta — o que foi feito —
     * e não a notícia burocrática de que um estado mudou.
     */
    private function avisarResolucao(Ticket $chamado, User $usuario, ?string $nota): void
    {
        $título = sprintf('Chamado %s resolvido: %s', $chamado->protocol, $chamado->subject);
        $link = route('tickets.show', $chamado);

        $destinos = User::query()
            ->where('company_id', $chamado->company_id)
            ->where('status', 'active')
            ->where(fn ($q) => $q
                ->where('id', $chamado->responsible_user_id)
                ->orWhere('client_id', $chamado->client_id))
            ->where('id', '!=', $usuario->id)
            ->get();

        foreach ($destinos as $destino) {
            Notifier::para($destino, 'chamdo.resolvido', $título, $nota, $link, [
                'ticket_id' => $chamado->id,
            ]);
        }
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

    /**
     * Estados que a ficha oferece: quem conduz o chamado é `tickets.execute`; o fluxo
     * do modelo decide o caminho e a permissão de encerramento decide se o passo é
     * deste usuário. Para a conta de cliente a lista vem vazia, então nenhum botão de
     * estado é desenhado na tela dela.
     *
     * @return array<string, string> slug => rótulo
     */
    private function proximosEstados(Ticket $chamado, User $usuario): array
    {
        if (! $usuario->hasPermission('tickets.execute')) {
            return [];
        }

        $podeFechar = $usuario->hasPermission('tickets.close');

        return collect(Ticket::FLUXO[$chamado->status] ?? [])
            ->reject(fn (string $destino) => in_array($destino, Ticket::ESTADOS_APROVADOS, true) && ! $podeFechar)
            ->mapWithKeys(fn (string $destino) => [$destino => StatusCatalog::label('ticket', $destino)])
            ->all();
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
     * O que a tela não decide, o servidor decide: a carteira de quem escreve e o
     * texto rico sanitized antes de virar bytes no banco.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function normaliza(array $dados, User $usuario): array
    {
        if ($usuario->client_id !== null) {
            $dados['client_id'] = $usuario->client_id;
        }

        if (array_key_exists('description', $dados)) {
            $dados['description'] = TextoSeguro::sanitizar($dados['description']);
        }

        return $dados;
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

    /**
     * Nota obrigatória nos passos que encerram: resolver sem dizer o que foi feito e
     * fechar sem resolução registrada são duas maneiras de perder o que aconteceu.
     */
    private function notaObrigatoria(Ticket $chamado, string $destino): bool
    {
        return $destino === 'resolved' || ($destino === 'closed' && $chamado->status !== 'resolved');
    }

    private function mensagemDaNota(string $destino): string
    {
        return $destino === 'resolved'
            ? 'Resolver um chamado pede o que foi feito: é a frase que o cliente vai ler.'
            : 'Fechar um chamado sem resolução registrada pede por que ele está sendo fechado.';
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
