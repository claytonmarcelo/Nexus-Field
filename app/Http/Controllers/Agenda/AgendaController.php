<?php

namespace App\Http\Controllers\Agenda;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\User;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A agenda da operação: o calendário onde a escala da empresa é lida e marcada.
 *
 * Duas coisas aparecem no mesmo quadro, e por motivos diferentes. O **compromisso**
 * é o que esta tela escreve — a janela reservada, com técnico, local e estado. A
 * **ordem de serviço agendada** é o que a tela lê da própria ordem, somente-leitura:
 * esconderla deixaria o calendário bonito e inútil, porque o dia de um técnico é
 * feito das ordens marcadas para ele, não só dos recados digitados à mão.
 *
 * O alcance é o do resto da casa: o técnico vê a própria escala, a conta de cliente
 * vê o que é do cliente dela, e o escritório vê a empresa. Isso vale para o JSON do
 * calendário tanto quanto vale para a ficha — o feed filtra antes de montar
 * qualquer evento, então não existe lista escondida por CSS.
 */
class AgendaController extends Controller
{
    /**
     * Teto da janela que o feed aceita. O calendário pede o que está desenhado, e
     * um `?fim=+10 anos` digitado na URL não pode virar varredura na tabela.
     */
    private const JANELA_MAXIMA_DIAS = 120;

    /**
     * Estados de ordem que ocupam a escala. Rascunho ainda não foi marcado para
     * ninguém e cancelada não acontece: nenhuma das duas deve aparecer como dia
     * reservado na agenda de um técnico.
     *
     * @var array<int, string>
     */
    private const ORDENS_AGENDADAS = ['open', 'in_progress', 'on_hold', 'completed'];

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $restrito = Appointment::alcanceRestrito($usuario);

        return view('agenda.index', [
            'restrito' => $restrito,
            'tecnicos' => $restrito ? [] : $this->tecnicos(),
            'tipos' => StatusCatalog::options('appointment_type'),
            'tecnico' => $restrito ? null : $this->opcaoPermitida($request, 'tecnico', $this->tecnicos()),
            'tipo' => $this->opcaoPermitida($request, 'tipo', StatusCatalog::options('appointment_type')),
            'podeCriar' => $usuario->hasPermission('agenda.create'),
            'podeMover' => $usuario->hasPermission('agenda.update'),
            'rotaFeed' => route('agenda.feed'),
            'rotaNovo' => $restrito && ! $usuario->hasPermission('agenda.create') ? '' : route('agenda.create'),
        ]);
    }

    /**
     * O JSON que o calendário lê. Vem numa chamada só porque o quadro mostra as
     * duas naturezas lado a lado: separar em dois pedidos deixaria a ordem e o
     * compromisso do mesmo dia chegando em momentos diferentes.
     */
    public function feed(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $validado = $request->validate([
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date'],
        ]);

        $inicio = Carbon::parse($validado['inicio']);
        $fim = Carbon::parse($validado['fim'])
            ->min($inicio->copy()->addDays(self::JANELA_MAXIMA_DIAS));

        return response()->json([
            ...$this->eventosDosCompromissos($request, $usuario, $inicio, $fim),
            ...$this->eventosDasOrdens($usuario, $inicio, $fim),
        ]);
    }

    public function create(Request $request): View
    {
        $usuario = $request->user();
        $compromisso = new Appointment([
            'type' => 'visit',
            'status' => 'scheduled',
            'all_day' => false,
        ]);

        // O calendário chega aqui por clique: a janela escolhida na tela vem na URL
        // e volta preenchida, porque remarcar começa pelo "quando" e não pelo "quê".
        $compromisso->starts_at = $this->dataLocal($request->query('inicio')) ?? now()->addHour()->startOfHour();
        $compromisso->ends_at = $this->dataLocal($request->query('fim')) ?? $compromisso->starts_at->copy()->addHour();

        if ($tecnico = $this->tecnicoDaUrl($request, $usuario)) {
            $compromisso->technician_id = $tecnico->id;
        }

        if ($ordem = $this->ordemDaUrl($request, $usuario)) {
            $compromisso->service_order_id = $ordem->id;
            $compromisso->client_id = $ordem->client_id;
            $compromisso->type = 'order';
        }

        return view('agenda.form', $this->dadosDaFicha($compromisso, $usuario) + [
            'compromisso' => $compromisso,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $dados = $request->validate($this->regras($request, $usuario), $this->mensagens());

        $compromisso = Appointment::create($this->normaliza($dados, $usuario));

        return redirect()
            ->route('agenda.show', $compromisso)
            ->with('status', 'Compromisso “'.$compromisso->title.'” agendado para '.$this->descreverJanela($compromisso).'.');
    }

    public function show(Request $request, Appointment $compromisso): View
    {
        $usuario = $request->user();
        $this->garantirVisivel($compromisso, $usuario);

        $compromisso->load(['technician', 'client', 'serviceOrder:id,number,title', 'ticket:id,protocol,subject']);

        return view('agenda.show', [
            'compromisso' => $compromisso,
            'proximosEstados' => $this->proximosEstados($compromisso, $usuario),
            'podeEditar' => $usuario->hasPermission('agenda.update') && ! $compromisso->estaTravado(),
        ]);
    }

    public function edit(Request $request, Appointment $compromisso): View
    {
        $usuario = $request->user();
        $this->garantirVisivel($compromisso, $usuario);

        if ($compromisso->estaTravado()) {
            return back()->with('erro', 'Compromisso concluído é fato passado: a janela dele não se edita mais.');
        }

        return view('agenda.form', $this->dadosDaFicha($compromisso, $usuario) + [
            'compromisso' => $compromisso,
        ]);
    }

    public function update(Request $request, Appointment $compromisso): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($compromisso, $usuario);

        if ($compromisso->estaTravado()) {
            return back()->with('erro', 'Compromisso concluído é fato passado: a janela dele não se edita mais.');
        }

        $compromisso->update($this->normaliza(
            $request->validate($this->regras($request, $usuario), $this->mensagens()), $usuario
        ));

        return redirect()
            ->route('agenda.show', $compromisso)
            ->with('status', 'Compromisso “'.$compromisso->title.'” atualizado.');
    }

    /**
     * Único caminho que muda o estado do compromisso. Sem trilha à parte: o que
     * mudou fica na auditoria, com autor e hora, como no resto do domínio.
     */
    public function mudarStatus(Request $request, Appointment $compromisso): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($compromisso, $usuario);

        $validado = $request->validate([
            'estado' => ['required', Rule::in(array_keys(StatusCatalog::options('appointment')))],
        ]);

        $destino = $validado['estado'];

        if (! $compromisso->podeMudarPara($destino)) {
            return back()->with('erro', sprintf(
                'O compromisso está “%s” e não pode ir para “%s”: o fluxo da agenda é o que vale.',
                StatusCatalog::label('appointment', $compromisso->status),
                StatusCatalog::label('appointment', $destino),
            ));
        }

        if (! array_key_exists($destino, $this->proximosEstados($compromisso, $usuario))) {
            return back()->with('erro', 'Esta conta não conduz o estado de um compromisso da agenda.');
        }

        $origem = StatusCatalog::label('appointment', $compromisso->status);
        $compromisso->update(['status' => $destino]);

        return back()->with('status', sprintf(
            'Compromisso “%s”: %s → %s.',
            $compromisso->title,
            $origem,
            StatusCatalog::label('appointment', $destino),
        ));
    }

    /**
     * Arrastar e soltar no calendário. É a rota que o JavaScript chama, e ela não
     * confia no que a tela desenhou: confere o alcance, o estado e a janela antes
     * de escrever qualquer hora.
     */
    public function reagendar(Request $request, Appointment $compromisso): JsonResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($compromisso, $usuario);

        if ($compromisso->estaTravado()) {
            return response()->json([
                'mensagem' => 'Compromisso concluído não muda mais de janela.',
            ], 422);
        }

        $validado = $request->validate([
            'inicio' => ['required', 'date'],
            'fim' => ['nullable', 'date'],
        ], [
            'fim.after' => 'A janela terminou antes de começar: arraste de novo.',
        ]);

        $novoInicio = Carbon::parse($validado['inicio']);

        if ($compromisso->all_day) {
            // Um compromisso de dia inteiro não tem hora a mover: o que o arraste
            // diz é em que dia ele cai. Desloca a janela inteira pelos dias de
            // diferença, preservando a duração que já estava gravada. O `copy()` não
            // é ornamento: sem ele a hora original seria zerada na própria modelo.
            $dias = $compromisso->starts_at->copy()->startOfDay()
                ->diffInDays($novoInicio->copy()->startOfDay(), false);

            $compromisso->update([
                'starts_at' => $compromisso->starts_at->copy()->addDays((int) $dias),
                'ends_at' => $compromisso->ends_at->copy()->addDays((int) $dias),
            ]);
        } else {
            // O arraste simples manda só o novo início; a duração fica a que estava
            // gravada. O resize manda os dois lados da janela.
            $fimBruto = $validado['fim'] ?? null;
            $novoFim = $fimBruto !== null
                ? Carbon::parse($fimBruto)
                : $novoInicio->copy()->addMinutes($compromisso->minutos());

            if (! $novoFim->greaterThan($novoInicio)) {
                return response()->json(['mensagem' => 'A janela terminou antes de começar: arraste de novo.'], 422);
            }

            $compromisso->update(['starts_at' => $novoInicio, 'ends_at' => $novoFim]);
        }

        return response()->json([
            'compromisso' => [
                'id' => $compromisso->id,
                'inicio' => $this->marca($compromisso->starts_at, false),
                'fim' => $this->marca($compromisso->ends_at, false),
                'janela' => $this->descreverJanela($compromisso),
            ],
        ]);
    }

    public function destroy(Request $request, Appointment $compromisso): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($compromisso, $usuario);

        $titulo = $compromisso->title;
        $compromisso->delete();

        return redirect()
            ->route('agenda.index')
            ->with('status', 'Compromisso “'.$titulo.'” removido da agenda.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventosDosCompromissos(Request $request, User $usuario, Carbon $inicio, Carbon $fim): array
    {
        $podeMover = $usuario->hasPermission('agenda.update');

        return $this->consultaCompromissos($request, $usuario, $inicio, $fim)
            ->get()
            ->map(fn (Appointment $compromisso) => [
                'id' => 'c'.$compromisso->id,
                'title' => $compromisso->title,
                'start' => $this->marca($compromisso->starts_at, $compromisso->all_day),
                // FullCalendar trata o fim de um evento de dia inteiro como exclusivo.
                'end' => $compromisso->all_day
                    ? $this->marca($compromisso->ends_at->copy()->addDay(), true)
                    : $this->marca($compromisso->ends_at, false),
                'allDay' => (bool) $compromisso->all_day,
                'url' => route('agenda.show', $compromisso),
                // Um compromisso concluído é fato passado: o quadro não oferece o
                // arraste que o servidor vai recusar, e o cursor não mente.
                'editable' => $podeMover && ! $compromisso->estaTravado(),
                'classNames' => [
                    'nf-fc-event',
                    'nf-fc-t--'.StatusCatalog::tone('appointment_type', $compromisso->type),
                    'nf-fc-s--'.StatusCatalog::tone('appointment', $compromisso->status),
                ],
                'extendedProps' => [
                    'natureza' => 'compromisso',
                    'tipo' => StatusCatalog::label('appointment_type', $compromisso->type),
                    'estado' => StatusCatalog::label('appointment', $compromisso->status),
                    'tecnico' => $compromisso->technician?->name,
                    'cliente' => $compromisso->client?->name,
                    'local' => $compromisso->location,
                    'travado' => $compromisso->estaTravado(),
                    'diaInteiro' => (bool) $compromisso->all_day,
                    // A rota do arraste vem do servidor, montada com o modelo: o
                    // JavaScript não precisa saber como o id do compromisso se escreve.
                    'janela' => route('agenda.reschedule', $compromisso),
                ],
            ])
            ->all();
    }

    /**
     * Ordens com janela marcada, lidas da própria ordem.
     *
     * @return array<int, array<string, mixed>>
     */
    private function eventosDasOrdens(User $usuario, Carbon $inicio, Carbon $fim): array
    {
        return ServiceOrder::query()
            ->visiveisPara($usuario)
            ->whereIn('status', self::ORDENS_AGENDADAS)
            ->whereNotNull('scheduled_starts_at')
            ->where('scheduled_starts_at', '<=', $fim)
            ->where(fn (Builder $lado) => $lado
                ->where('scheduled_ends_at', '>=', $inicio)
                ->orWhere(fn (Builder $semFim) => $semFim
                    ->whereNull('scheduled_ends_at')
                    ->where('scheduled_starts_at', '>=', $inicio)))
            ->with(['client:id,name', 'assignments.technician:id,name'])
            ->orderBy('scheduled_starts_at')
            ->get()
            ->map(fn (ServiceOrder $ordem) => [
                'id' => 'o'.$ordem->id,
                'title' => $ordem->number.' · '.$ordem->title,
                'start' => $this->marca($ordem->scheduled_starts_at, false),
                'end' => $this->marca($ordem->scheduled_ends_at, false),
                'allDay' => false,
                'url' => route('orders.show', $ordem),
                // Somente-leitura de propósito: a janela da ordem é movida na ficha
                // dela, onde o motivo do remanejamento é registrado junto.
                'editable' => false,
                'classNames' => [
                    'nf-fc-event',
                    'nf-fc-event--ordem',
                    'nf-fc-s--'.StatusCatalog::tone('order', $ordem->status),
                ],
                'extendedProps' => [
                    'natureza' => 'ordem',
                    'tipo' => 'Ordem agendada',
                    'estado' => StatusCatalog::label('order', $ordem->status),
                    'tecnico' => $ordem->assignments->pluck('technician.name')->filter()->implode(', ') ?: null,
                    'cliente' => $ordem->client?->name,
                    'local' => $this->localDaOrdem($ordem),
                ],
            ])
            ->all();
    }

    /**
     * Endereço da ordem montado para o hover do calendário. A ordem guarda a rua e a
     * cidade nas próprias colunas — não há tabela de endereço dela.
     */
    private function localDaOrdem(ServiceOrder $ordem): ?string
    {
        $rua = collect([$ordem->street, $ordem->number_address])->filter()->implode(', ');

        return collect([$rua ?: null, $ordem->neighborhood, $ordem->city])
            ->filter()
            ->implode(' · ') ?: null;
    }

    /**
     * A consulta da agenda: alcance antes de filtro, para um `?tecnico=99` digitado
     * na URL não abrir a escala de outro técnico.
     */
    private function consultaCompromissos(Request $request, User $usuario, Carbon $inicio, Carbon $fim): Builder
    {
        $query = Appointment::query()
            ->visiveisPara($usuario)
            ->between($inicio, $fim)
            ->with(['technician:id,name', 'client:id,name']);

        if (($tecnico = $this->opcaoPermitida($request, 'tecnico', $this->tecnicos())) !== null) {
            $query->where('technician_id', $tecnico);
        }

        if (($tipo = $this->opcaoPermitida($request, 'tipo', StatusCatalog::options('appointment_type'))) !== null) {
            $query->where('type', $tipo);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosDaFicha(Appointment $compromisso, User $usuario): array
    {
        // A ficha é do escritório, mas o alcance continua mandando nas listas: quem
        // tem vínculo de técnico ou de cliente não ganha a escala inteira da empresa
        // só porque abriu um formulário.
        $restrito = Appointment::alcanceRestrito($usuario);

        return [
            'clientes' => $restrito ? [] : $this->clientes(),
            'tecnicos' => $restrito ? [] : $this->tecnicos(),
            'tipos' => StatusCatalog::options('appointment_type'),
            'ordens' => $this->ordensParaFicha($usuario),
            'chamados' => $this->chamadosParaFicha($usuario),
            'restrito' => $restrito,
        ];
    }

    /**
     * @return array<string, string> slug => rótulo
     */
    private function proximosEstados(Appointment $compromisso, User $usuario): array
    {
        if (! $usuario->hasPermission('agenda.update')) {
            return [];
        }

        return collect(Appointment::FLUXO[$compromisso->status] ?? [])
            ->mapWithKeys(fn (string $destino) => [$destino => StatusCatalog::label('appointment', $destino)])
            ->all();
    }

    private function garantirVisivel(Appointment $compromisso, User $usuario): void
    {
        abort_unless(
            Appointment::query()->visiveisPara($usuario)->whereKey($compromisso->getKey())->exists(),
            403,
            'Este compromisso não está na sua agenda.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function regras(Request $request, User $usuario): array
    {
        $empresa = TenantContext::id();

        // Dia inteiro pode começar e terminar no mesmo dia; compromisso com hora não
        // tem janela de duração zero. A regra segue o que a tela marcou agora, não o
        // que estava gravado antes.
        $regraFim = $request->boolean('all_day') ? 'after_or_equal:starts_at' : 'after:starts_at';

        return [
            'title' => ['required', 'string', 'max:180'],
            'type' => ['required', Rule::in(array_keys(StatusCatalog::options('appointment_type')))],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', $regraFim],
            'all_day' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'technician_id' => ['nullable', Rule::exists('technicians', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa)
                    ->where('status', '!=', 'inactive')
                    ->whereNull('deleted_at'))],
            'client_id' => ['nullable', Rule::exists('clients', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at'))],
            'service_order_id' => ['nullable', Rule::exists('service_orders', 'id')
                ->where(function ($query) use ($empresa, $usuario): void {
                    $query->where('company_id', $empresa)
                        ->whereIn('id', ServiceOrder::query()->visiveisPara($usuario)->select('id'));
                })],
            'ticket_id' => ['nullable', Rule::exists('tickets', 'id')
                ->where(function ($query) use ($empresa, $usuario): void {
                    $query->where('company_id', $empresa)
                        ->whereIn('id', Ticket::query()->visiveisPara($usuario)->select('id'));
                })],
        ];
    }

    /**
     * O que a tela não decide, o servidor decide: o cliente herdado daquilo que o
     * compromisso prende e a carteira de quem escreve. A empresa entra pela trait,
     * e o estado não passa por aqui — mover estado é botão da ficha.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function normaliza(array $dados, User $usuario): array
    {
        if ($usuario->client_id !== null) {
            $dados['client_id'] = $usuario->client_id;
        }

        // Prender o compromisso a uma ordem ou a um chamado sem levar o cliente
        // junto deixaria a agenda dizer uma coisa e a ficha dizer outra.
        if (! empty($dados['service_order_id'])) {
            $dados['client_id'] = ServiceOrder::query()->findOrFail($dados['service_order_id'])->client_id;
        } elseif (! empty($dados['ticket_id'])) {
            $dados['client_id'] = Ticket::query()->findOrFail($dados['ticket_id'])->client_id;
        }

        return $dados;
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'ends_at.after' => 'A janela precisa terminar depois de começar.',
            'ends_at.after_or_equal' => 'Um compromisso de dia inteiro não pode terminar antes do dia em que começa.',
            'technician_id.exists' => 'Este técnico não está disponível para receber janelas na agenda.',
            'client_id.exists' => 'O cliente precisa ser um cadastro da sua empresa.',
            'service_order_id.exists' => 'A ordem precisa ser sua ou da sua empresa.',
            'ticket_id.exists' => 'O chamado precisa ser seu ou da sua empresa.',
            'type.in' => 'O tipo precisa ser um dos que a agenda conhece.',
        ];
    }

    /** @return array<int, string> */
    private function tecnicos(): array
    {
        return Technician::query()->where('status', '!=', 'inactive')->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    private function clientes(): array
    {
        return Client::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    private function ordensParaFicha(User $usuario): array
    {
        return ServiceOrder::query()
            ->visiveisPara($usuario)
            ->latest('created_at')
            ->get()
            ->mapWithKeys(fn (ServiceOrder $ordem) => [$ordem->id => $ordem->number.' · '.$ordem->title])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function chamadosParaFicha(User $usuario): array
    {
        return Ticket::query()
            ->visiveisPara($usuario)
            ->latest('opened_at')
            ->get()
            ->mapWithKeys(fn (Ticket $chamado) => [$chamado->id => $chamado->protocol.' · '.$chamado->subject])
            ->all();
    }

    /** Um filtro só vale se estiver na lista que a própria tela desenha. */
    private function opcaoPermitida(Request $request, string $param, array $opcoes): ?string
    {
        $valor = trim((string) $request->query($param));

        return $valor !== '' && array_key_exists($valor, $opcoes) ? $valor : null;
    }

    private function tecnicoDaUrl(Request $request, User $usuario): ?Technician
    {
        if (Appointment::alcanceRestrito($usuario)) {
            return null;
        }

        $id = $this->opcaoPermitida($request, 'tecnico', $this->tecnicos());

        return $id === null ? null : Technician::query()->find((int) $id);
    }

    private function ordemDaUrl(Request $request, User $usuario): ?ServiceOrder
    {
        $id = $request->query('ordem');

        return ctype_digit((string) $id)
            ? ServiceOrder::query()->visiveisPara($usuario)->whereKey((int) $id)->first()
            : null;
    }

    /**
     * Hora do calendário em relógio de parede. O banco guarda a hora da operação
     * (`APP_TIMEZONE`), e é esse relógio que o quadro precisa desenhar — por isso o
     * formato sai sem fuso e sem `Z`, e não o ISO com deslocamento do navegador.
     */
    private function marca(?Carbon $momento, bool $diaInteiro): ?string
    {
        return $momento?->format($diaInteiro ? 'Y-m-d' : 'Y-m-d\TH:i:s');
    }

    private function dataLocal(mixed $valor): ?Carbon
    {
        return filled($valor) ? Carbon::parse(strval($valor)) : null;
    }

    private function descreverJanela(Appointment $compromisso): string
    {
        if ($compromisso->all_day) {
            return 'o dia '.$compromisso->starts_at->format('d/m/Y');
        }

        return $compromisso->starts_at->format('d/m/Y á\s H:i')
            .' até '.($compromisso->ends_at->isSameDay($compromisso->starts_at)
                ? $compromisso->ends_at->format('H:i')
                : $compromisso->ends_at->format('d/m/Y á\s H:i'));
    }
}
