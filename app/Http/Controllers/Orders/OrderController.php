<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Concerns\EmEdicao;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Orders\Concerns\EnxergaAOrdem;
use App\Models\Client;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatusHistory;
use App\Models\Team;
use App\Models\Technician;
use App\Models\User;
use App\Support\Export;
use App\Support\Formatters;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ordens de serviço da empresa. Aqui a listagem tem uma regra a mais que em
 * qualquer outra tela: quem enxerga depende de quem é. O técnico recebe as ordens
 * dele, a conta de cliente recebe a carteira dela, e o escritório recebe a
 * operação inteira — o mesmo alcance vale para a ficha, a exportação e a URL
 * direta, porque é a consulta que devolve só o que é dele, não o HTML que esconde.
 */
class OrderController extends Controller
{
    use EmEdicao, EnxergaAOrdem;

    private const ORDENAVEIS = [
        'number', 'title', 'priority', 'status', 'scheduled_starts_at', 'scheduled_ends_at', 'created_at',
    ];

    private const FILTROS = ['busca', 'situacao', 'prioridade', 'tecnico', 'cliente', 'inicio', 'fim'];

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $restrito = ServiceOrder::alcanceRestrito($usuario);

        return view('orders.index', [
            'ordens' => $this->consulta($request, $usuario)
                ->withTotals()
                ->with(['client:id,name', 'technician:id,name', 'service:id,name'])
                ->withCount('items')
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'scheduled_starts_at', 'desc'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'situacoes' => StatusCatalog::options('order'),
            'prioridades' => StatusCatalog::options('priority'),
            'tecnicos' => $restrito ? [] : $this->tecnicos(),
            'clientes' => $restrito ? [] : $this->clientes(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
            'restrito' => $restrito,
        ]);
    }

    public function create(Request $request): View
    {
        $ordem = new ServiceOrder([
            'priority' => 'normal',
            'status' => $request->user()->hasPermission('orders.approve') ? 'open' : 'draft',
        ]);

        $cliente = $this->clientePreferido($request);

        if ($cliente !== null) {
            $ordem->client_id = $cliente->id;
            $ordem->setRelation('client', $cliente);
            $this->copiarEndereco($ordem, $cliente);
        }

        return view('orders.form', [
            'ordem' => $ordem,
            'clientes' => $this->clientes(),
            'servicos' => $this->servicos(),
            'tecnicos' => $this->tecnicos(),
            'equipas' => $this->equipas(),
            'prioridades' => StatusCatalog::options('priority'),
            'situacoes' => $this->estadosIniciais($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $dados = $request->validate($this->regras($usuario), $this->mensagens());

        $ordem = DB::transaction(function () use ($dados, $usuario): ServiceOrder {
            $ordem = ServiceOrder::create([
                ...$this->normaliza($dados),
                'number' => ServiceOrder::proximoNumero(),
            ]);

            ServiceOrderStatusHistory::query()->create([
                'service_order_id' => $ordem->id,
                'user_id' => $usuario->id,
                'from_status' => null,
                'to_status' => $ordem->status,
                'note' => 'Ordem aberta na tela de ordens.',
                'created_at' => now(),
            ]);

            if ($ordem->technician_id !== null) {
                $ordem->assignments()->create([
                    'technician_id' => $ordem->technician_id,
                    'assigned_by' => $usuario->id,
                    'assigned_at' => now(),
                ]);
            }

            return $ordem;
        });

        return redirect()
            ->route('orders.show', $ordem)
            ->with('status', "Ordem {$ordem->number} criada para {$ordem->client->name}.");
    }

    public function show(Request $request, ServiceOrder $ordem): View
    {
        $usuario = $request->user();
        $this->garantirVisivel($ordem, $usuario);

        $ordem->load([
            'client', 'service.category', 'technician', 'team',
            'items.service', 'items.product',
            'assignments.technician', 'assignments.assignedBy',
            'statusHistory.user', 'checkins.technician',
        ]);

        return view('orders.show', [
            'ordem' => $ordem,
            'proximosEstados' => $this->proximosEstados($ordem, $usuario),
            'itemEmEdicao' => $this->emEdicao($request, 'editar_item', $ordem->items),
            'servicos' => $this->servicos(),
            'produtos' => $this->produtos(),
            'tecnicos' => $this->tecnicos(),
        ]);
    }

    public function edit(Request $request, ServiceOrder $ordem): View
    {
        $this->garantirVisivel($ordem, $request->user());

        $ordem->loadMissing('client', 'items');

        return view('orders.form', [
            'ordem' => $ordem,
            'clientes' => $this->clientes(),
            'servicos' => $this->servicos(),
            'tecnicos' => $this->tecnicos(),
            'equipas' => $this->equipas(),
            'prioridades' => StatusCatalog::options('priority'),
            'situacoes' => [],
        ]);
    }

    public function update(Request $request, ServiceOrder $ordem): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($ordem, $usuario);

        $ordem->loadMissing('items');
        $dados = $request->validate($this->regras($usuario, $ordem), $this->mensagens());

        $ordem->update($this->normaliza($dados));

        return redirect()
            ->route('orders.show', $ordem)
            ->with('status', "Ordem {$ordem->number} atualizada.");
    }

    /**
     * O único caminho que muda o estado depois da criação. Além de trocar a
     * coluna, põe o carimbo do momento e grava a passagem com quem fez: é isso
     * que responde "quem abriu esta ordem e quando" daqui a três meses.
     */
    public function mudarStatus(Request $request, ServiceOrder $ordem): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($ordem, $usuario);

        $validado = $request->validate([
            'estado' => ['required', Rule::in(array_keys(StatusCatalog::options('order')))],
            'nota' => ['required_if:estado,canceled', 'nullable', 'string', 'max:1000'],
        ], [
            'nota.required_if' => 'Cancelar uma ordem sem registrar o motivo não é cancelamento.',
        ]);

        $destino = $validado['estado'];

        if (! $ordem->podeMudarPara($destino)) {
            return back()->with('erro', sprintf(
                'A ordem %s está “%s” e não pode ir para “%s”: o fluxo do módulo é o que vale.',
                $ordem->number,
                StatusCatalog::label('order', $ordem->status),
                StatusCatalog::label('order', $destino),
            ));
        }

        if (! array_key_exists($destino, $this->proximosEstados($ordem, $usuario))) {
            return back()->with('erro', 'Tirar um rascunho do papel e cancelar uma ordem pedem a permissão de aprovação.');
        }

        $origem = StatusCatalog::label('order', $ordem->status);
        $ordem->mudarStatus($destino, $usuario, $validado['nota'] ?? null);

        return back()->with('status', sprintf(
            'Ordem %s: %s → %s.',
            $ordem->number,
            $origem,
            StatusCatalog::label('order', $destino),
        ));
    }

    /**
     * Ordem que saiu do rascunho tem estado, carimbo e gente que trabalhou nela —
     * apagá-la riscaria uma operação real. O caminho é cancelar com motivo; a
     * exclusão só vale para o rascunho que nunca virou trabalho.
     */
    public function destroy(ServiceOrder $ordem): RedirectResponse
    {
        if ($ordem->status !== 'draft') {
            return redirect()
                ->route('orders.show', $ordem)
                ->with('erro', sprintf(
                    'A ordem %s não é mais rascunho (%s), então não pode ser apagada. Cancele-a com o motivo registrado.',
                    $ordem->number,
                    StatusCatalog::label('order', $ordem->status),
                ));
        }

        $numero = $ordem->number;
        $ordem->delete();

        return redirect()
            ->route('orders.index')
            ->with('status', "Rascunho {$numero} apagado.");
    }

    public function export(Request $request): StreamedResponse
    {
        $usuario = $request->user();

        return Export::csv(
            'ordens-de-servico',
            ['Número', 'Título', 'Cliente', 'Técnico', 'Estado', 'Prioridade', 'Dia agendado',
                'Início previsto', 'Fim previsto', 'Início real', 'Conclusão', 'Itens', 'Total', 'Local', 'Aberta em'],
            $this->consulta($request, $usuario)
                ->withTotals()
                ->with(['client:id,name', 'technician:id,name'])
                ->withCount('items')
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'scheduled_starts_at', 'desc'))
                ->lazyById(200)
                ->map($this->linhaCsv(...)),
        );
    }

    /** @return array<int, mixed> */
    private function linhaCsv(ServiceOrder $ordem): array
    {
        $totais = $ordem->totais();

        return [
            $ordem->number,
            $ordem->title,
            $ordem->client->name,
            $ordem->technician?->name,
            StatusCatalog::label('order', $ordem->status),
            StatusCatalog::label('priority', $ordem->priority),
            $ordem->scheduled_starts_at?->toDateString(),
            $ordem->scheduled_starts_at,
            $ordem->scheduled_ends_at,
            $ordem->started_at,
            $ordem->completed_at,
            $ordem->items_count,
            Formatters::money($totais['total']),
            trim(($ordem->city ?? '').($ordem->state ? '/'.$ordem->state : ''), '/'),
            $ordem->created_at,
        ];
    }

    /**
     * A consulta da listagem, da exportação e de qualquer contagem do módulo. O
     * alcance do usuário entra antes dos filtros: um técnico que digitar
     * `?cliente=99` na URL não chega à linha de outro técnico.
     */
    private function consulta(Request $request, User $usuario): Builder
    {
        $query = ServiceOrder::query()->visiveisPara($usuario);

        $query = ListFilters::igual($query, $request, 'situacao', 'status', array_keys(StatusCatalog::options('order')));
        $query = ListFilters::igual($query, $request, 'prioridade', 'priority', array_keys(StatusCatalog::options('priority')));
        $query = ListFilters::busca($query, $request, ['number', 'title', 'description', 'street', 'city']);
        $query = ListFilters::relacionado($query, $request, 'cliente', 'client');
        $query = ListFilters::relacionado($query, $request, 'tecnico', 'technician');

        return ListFilters::periodo($query, $request, 'scheduled_starts_at');
    }

    /**
     * Estados que a ficha oferece como botão: o fluxo do modelo decide o caminho
     * e a permissão de aprovação decide se o passo é deste usuário.
     *
     * @return array<string, string> slug => rótulo
     */
    private function proximosEstados(ServiceOrder $ordem, User $usuario): array
    {
        $podeAprovar = $usuario->hasPermission('orders.approve');

        return collect(ServiceOrder::FLUXO[$ordem->status] ?? [])
            ->reject(fn (string $destino) => in_array($destino, ServiceOrder::ESTADOS_APROVADOS, true) && ! $podeAprovar)
            ->mapWithKeys(fn (string $destino) => [$destino => StatusCatalog::label('order', $destino)])
            ->all();
    }

    /** @return array<string, string> */
    private function estadosIniciais(User $usuario): array
    {
        $aceitos = $usuario->hasPermission('orders.approve')
            ? ServiceOrder::ESTADOS_INICIAIS
            : ['draft'];

        return array_intersect_key(StatusCatalog::options('order'), array_flip($aceitos));
    }

    /** @return array<int, string> */
    private function clientes(): array
    {
        return Client::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    private function servicos(): array
    {
        return Service::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    private function produtos(): array
    {
        return Product::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    private function tecnicos(): array
    {
        return Technician::query()->where('status', '!=', 'inactive')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    private function equipas(): array
    {
        return Team::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** O cliente que a ficha de origem apontou em `?cliente=`; nada além dele. */
    private function clientePreferido(Request $request): ?Client
    {
        $id = $request->query('cliente');

        return ctype_digit((string) $id) ? Client::query()->find((int) $id) : null;
    }

    /**
     * A ordem carrega o endereço do dia do serviço, copiado do cadastro: se o
     * cliente trocar de sala amanhã, a ordem velha continua dizendo onde o
     * técnico esteve.
     */
    private function copiarEndereco(ServiceOrder $ordem, Client $cliente): void
    {
        $endereco = $cliente->enderecoPrincipal();

        if ($endereco === null) {
            return;
        }

        $ordem->fill([
            'street' => $endereco->street,
            'number_address' => $endereco->number,
            'neighborhood' => $endereco->neighborhood,
            'city' => $endereco->city,
            'state' => $endereco->state,
            'zip_code' => $endereco->zip_code,
            'latitude' => $endereco->latitude,
            'longitude' => $endereco->longitude,
        ]);
    }

    /**
     * O que a tela não pergunta, o banco responde: fim previsto pela duração
     * estimada do serviço e UF sempre em duas maiúsculas.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function normaliza(array $dados): array
    {
        $inicio = $dados['scheduled_starts_at'] ?? null;
        $fim = $dados['scheduled_ends_at'] ?? null;

        if ($inicio !== null && $fim === null && filled($dados['service_id'] ?? null)) {
            $duracao = Service::query()->whereKey($dados['service_id'])->value('estimated_minutes');

            if ($duracao !== null) {
                $fim = Carbon::parse($inicio)->addMinutes((int) $duracao)->toDateTimeString();
            }
        }

        $dados['scheduled_ends_at'] = $fim;

        if (filled($dados['state'] ?? null)) {
            $dados['state'] = mb_strtoupper($dados['state']);
        }

        return $dados;
    }

    /**
     * @return array<string, mixed>
     */
    private function regras(User $usuario, ?ServiceOrder $ordem = null): array
    {
        $empresa = TenantContext::id();
        $limite = $ordem?->totais()['liquido'];

        return [
            'client_id' => ['required', Rule::exists('clients', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at'))],
            'service_id' => ['nullable', Rule::exists('services', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at'))],
            'team_id' => ['nullable', Rule::exists('teams', 'id')->where('company_id', $empresa)],
            'technician_id' => ['nullable', Rule::exists('technicians', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at'))],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(StatusCatalog::options('priority')))],
            'scheduled_starts_at' => ['nullable', 'date'],
            'scheduled_ends_at' => ['nullable', 'date', 'after:scheduled_starts_at'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', $this->descontoPossivel($limite)],
            'street' => ['nullable', 'string', 'max:180'],
            'number_address' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'execution_notes' => ['nullable', 'string', 'max:5000'],
        ] + ($ordem === null ? [
            // No nascimento a ordem é rascunho ou aberta, e nada além: execução,
            // espera, conclusão e cancelamento são do botão de estado.
            'status' => ['required', Rule::in(array_keys($this->estadosIniciais($usuario)))],
        ] : []);
    }

    private function descontoPossivel(?float $limite): Closure
    {
        return function (string $atributo, $valor, Closure $falha) use ($limite): void {
            if ($limite === null || (float) $valor <= $limite) {
                return;
            }

            $falha(sprintf(
                'O desconto da ordem (%s) passa do que os itens cobram (%s).',
                Formatters::money((float) $valor),
                Formatters::money($limite),
            ));
        };
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'client_id.exists' => 'O cliente precisa ser um cadastro da sua empresa.',
            'service_id.exists' => 'O serviço precisa ser um item do seu catálogo.',
            'technician_id.exists' => 'O técnico precisa estar na sua escala.',
            'team_id.exists' => 'A equipe precisa ser da sua empresa.',
            'scheduled_ends_at.after' => 'O fim previsto precisa vir depois do início previsto.',
            'state.size' => 'A UF tem duas letras.',
        ];
    }
}
