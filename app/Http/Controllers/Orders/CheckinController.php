<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Orders\Concerns\EnxergaAOrdem;
use App\Models\Client;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderCheckin;
use App\Models\Technician;
use App\Models\User;
use App\Support\Auditor;
use App\Support\Distancia;
use App\Support\Export;
use App\Support\Formatters;
use App\Support\ListFilters;
use App\Support\StatusCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A presença do técnico no endereço da ordem: a chegada e a saída, cada uma com o
 * que o aparelho respondeu de posição.
 *
 * Três decisões mandam na tela inteira, e as três são de servidor.
 *
 * **A hora é do relógio de cá.** O `checkin_at` e o `checkout_at` nascem do
 * `now()` do aplicativo, nunca do navegador: hora lida do aparelho daria a quem
 * tem interesse direto no resultado o poder de escolher o horário em que diz ter
 * chegado. O que o aparelho manda, e só isso, é a coordenada.
 *
 * **Quem esteve lá não se digita no formulário.** Não existe `technician_id` no
 * payload: a conta de técnico responde pela própria ficha, e o escritório que tem
 * a aprovação da escala registra pelo responsável da ordem. Registrar presença em
 * campo é ato de quem pode responder por ela.
 *
 * **Distância é conta, não campo.** O servidor mede o vão entre a posição lida e o
 * endereço gravado na ordem, guarda o metro e compara com o raio que a empresa
 * escolheu. Um `distancia` vindo do cliente seria a prova fabricada pelo próprio
 * interessado.
 *
 * Não existe edição de carimbo nem de coordenada, e não existe exclusão. O que o
 * campo mediu é histórico: uma visita marcada errada se responde com outra visita
 * e a trilha de auditoria mostra quem fez o quê, não riscando a primeira.
 *
 * Visita sem GPS existe e é legítima: prédio, subsolo e carro derrubam a leitura.
 * Ela entra na trilha marcada como sem posição, e a tela diz isso com as mesmas
 * palavras que o banco guarda — recusar a chegada por falta de satélite ensinaria
 * o técnico a inventar coordenada.
 */
class CheckinController extends Controller
{
    use EnxergaAOrdem;

    /** Estados de ordem que aceitam presença em campo: rascunho ainda não saiu da mesa. */
    private const ORDENS_ACEITAM_CHEGADA = ['open', 'in_progress', 'on_hold'];

    private const ORDENAVEIS = ['checkin_at', 'checkout_at', 'status', 'created_at'];

    private const FILTROS = ['busca', 'situacao', 'tecnico', 'cliente', 'ordem', 'sem_posicao', 'inicio', 'fim'];

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $restrito = ServiceOrderCheckin::alcanceRestrito($usuario);
        $ordem = ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'checkin_at', 'desc');

        return view('checkins.index', [
            'visitas' => $this->consulta($request, $usuario)
                ->with([
                    'serviceOrder:id,company_id,client_id,number,title',
                    'serviceOrder.client:id,name',
                    'technician:id,name',
                ])
                ->orderBy(...$ordem)
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'situacoes' => StatusCatalog::options('checkin'),
            'tecnicos' => $restrito ? [] : $this->tecnicos(),
            'clientes' => $restrito ? [] : $this->clientes(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
            'restrito' => $restrito,
            'raio' => ServiceOrderCheckin::raioAceito(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $usuario = $request->user();
        $ordem = ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'checkin_at', 'desc');

        return Export::csv(
            'visitas-de-campo',
            ['Ordem', 'Cliente', 'Técnico', 'Entrada', 'Saída', 'Tempo no local',
                'Posição de entrada', 'Distância do endereço', 'Fora do raio',
                'Posição de saída', 'Distância da saída', 'Estado', 'Relato'],
            $this->consulta($request, $usuario)
                ->with(['serviceOrder:id,company_id,client_id,number', 'serviceOrder.client:id,name', 'technician:id,name'])
                ->orderBy(...$ordem)
                ->lazyById(200)
                ->map($this->linhaCsv(...)),
        );
    }

    /**
     * Chegada em campo. A rota está aninhada na ordem — `{ordem}/chegada` — porque
     * é a ordem que responde pelo local, pelo responsável e pelo endereço medido.
     */
    public function store(Request $request, ServiceOrder $ordem): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($ordem, $usuario);

        if (! in_array($ordem->status, self::ORDENS_ACEITAM_CHEGADA, true)) {
            return redirect()->route('orders.show', $ordem)->with('erro', $ordem->estaEncerrada()
                ? sprintf(
                    'A ordem %s já foi encerrada: presença em campo não se registra depois do fim do trabalho.',
                    $ordem->number,
                )
                : sprintf(
                    'A ordem %s ainda é rascunho: libere-a para o campo antes de registrar a chegada.',
                    $ordem->number,
                ));
        }

        $validado = $request->validate($this->regras(), $this->mensagens());
        $tecnico = $this->quemEsteve($ordem, $usuario);

        if ($ordem->checkins()->where('technician_id', $tecnico->id)->open()->exists()) {
            return back()->with('aviso', sprintf(
                '%s já está em campo nesta ordem: registre a saída antes de marcar uma nova chegada.',
                $tecnico->name,
            ));
        }

        $medida = Distancia::metros(
            $validado['latitude'] ?? null,
            $validado['longitude'] ?? null,
            $ordem->latitude,
            $ordem->longitude,
        );

        $visita = DB::transaction(function () use ($ordem, $usuario, $tecnico, $validado, $medida): ServiceOrderCheckin {
            $visita = ServiceOrderCheckin::query()->create([
                'service_order_id' => $ordem->id,
                'technician_id' => $tecnico->id,
                'checkin_at' => now(),
                'checkin_latitude' => $validado['latitude'] ?? null,
                'checkin_longitude' => $validado['longitude'] ?? null,
                'checkin_distance' => $medida,
                'status' => 'open',
                'observation' => $validado['observacao'] ?? null,
            ]);

            // A chegada abre a execução pelo fluxo da ordem, com a passagem e o
            // autor na trilha — o mesmo caminho do botão de estado, não um atalho
            // que troca a coluna por fora.
            if ($ordem->podeMudarPara('in_progress')) {
                $ordem->mudarStatus('in_progress', $usuario, 'Chegada registrada em campo pelo check-in.');
            }

            return $visita;
        });

        Auditor::gravar('chegada em campo', $visita, [], sprintf(
            '%s: %s chegou ao local%s.',
            $ordem->number,
            $tecnico->name,
            $this->resumoDaMedida($medida, $ordem),
        ));

        return redirect()->route('orders.show', $ordem)->with('status', sprintf(
            'Chegada de %s registrada às %s em %s.',
            $tecnico->name,
            Formatters::time($visita->checkin_at),
            $ordem->number,
        ));
    }

    /**
     * Saída. Encerra a passagem e mede o segundo ponto, mas não encerra a ordem:
     * ir embora não é terminar o serviço — concluir é decisão com estado próprio,
     * nota e quem aprova.
     */
    public function checkout(Request $request, ServiceOrderCheckin $visita): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisitaVisivel($visita, $usuario);
        $this->garantirResponsavel($visita, $usuario);

        if (! $visita->estaAberto()) {
            return back()->with('aviso', sprintf(
                'A saída desta visita já foi registrada em %s.',
                Formatters::dateTime($visita->checkout_at),
            ));
        }

        $validado = $request->validate($this->regras(), $this->mensagens());
        $ordem = $visita->serviceOrder()->firstOrFail();

        $medida = Distancia::metros(
            $validado['latitude'] ?? null,
            $validado['longitude'] ?? null,
            $ordem->latitude,
            $ordem->longitude,
        );

        $visita->update([
            'checkout_at' => now(),
            'checkout_latitude' => $validado['latitude'] ?? null,
            'checkout_longitude' => $validado['longitude'] ?? null,
            'checkout_distance' => $medida,
            'status' => 'closed',
            'observation' => filled($validado['observacao'] ?? null)
                ? $validado['observacao']
                : $visita->observation,
        ]);

        Auditor::gravar('saída em campo', $visita, [], sprintf(
            '%s: %s deixou o local após %s%s.',
            $ordem->number,
            $visita->technician->name,
            Formatters::duration($visita->duracaoMinutos()),
            $medida === null ? '' : sprintf(' (%s m do endereço)', Formatters::decimal($medida)),
        ));

        return redirect()->route('orders.show', $ordem)->with('status', sprintf(
            'Saída registrada às %s: %s no local em %s.',
            Formatters::time($visita->checkout_at),
            $visita->technician->name,
            Formatters::duration($visita->duracaoMinutos()),
        ));
    }

    /**
     * A consulta da listagem, da exportação e do painel do campo. O alcance entra
     * antes dos filtros: um técnico que digitar `?tecnico=99` na URL não chega à
     * visita de outro técnico.
     */
    private function consulta(Request $request, User $usuario): Builder
    {
        $query = ServiceOrderCheckin::query()->visiveisPara($usuario);

        $query = ListFilters::igual(
            $query,
            $request,
            'situacao',
            'status',
            array_keys(StatusCatalog::options('checkin')),
        );
        $query = ListFilters::relacionado($query, $request, 'tecnico', 'technician');
        $query = ListFilters::relacionado($query, $request, 'cliente', 'serviceOrder.client');
        $query = ListFilters::relacionado($query, $request, 'ordem', 'serviceOrder');

        $termo = trim((string) $request->query('busca'));

        if ($termo !== '') {
            $como = ListFilters::como($termo);

            $query->where(fn (Builder $lado) => $lado
                ->where('observation', 'like', $como)
                ->orWhereHas('serviceOrder', fn (Builder $ordem) => $ordem
                    ->where('number', 'like', $como)
                    ->orWhere('title', 'like', $como))
                ->orWhereHas('technician', fn (Builder $tecnico) => $tecnico->where('name', 'like', $como)));
        }

        if (filled($request->query('sem_posicao'))) {
            $query = $query->semPosicao();
        }

        return ListFilters::periodo($query, $request, 'checkin_at');
    }

    /**
     * Quem é o técnico da passagem. A conta de técnico responde pela própria ficha
     * — o formulário não tem campo de técnico justamente para não haver o que
     * forjar. O escritório que registra pelo responsável da ordem precisa da
     * aprovação da escala, que é o degrau acima de conduzir a própria execução.
     */
    private function quemEsteve(ServiceOrder $ordem, User $usuario): Technician
    {
        if ($tecnico = $usuario->technician) {
            return $tecnico;
        }

        abort_unless(
            $usuario->hasPermission('orders.approve'),
            403,
            'Registrar presença em campo é de quem esteve no local ou de quem responde pela escala.',
        );

        if ($ordem->technician === null) {
            throw ValidationException::withMessages([
                'ordem' => sprintf(
                    'A ordem %s não tem técnico responsável: comissione alguém antes de registrar a chegada.',
                    $ordem->number,
                ),
            ]);
        }

        return $ordem->technician;
    }

    private function garantirVisitaVisivel(ServiceOrderCheckin $visita, User $usuario): void
    {
        abort_unless(
            ServiceOrderCheckin::query()->visiveisPara($usuario)->whereKey($visita->id)->exists(),
            404,
            'Esta visita não está no seu alcance.',
        );
    }

    /**
     * Escrever numa visita já aberta é do técnico que esteve lá — ou de quem pode
     * responder por uma escala alheia, que é a aprovação da ordem. Um técnico com
     * `orders.update` na própria ficha não fecha a passagem do colega por engano.
     */
    private function garantirResponsavel(ServiceOrderCheckin $visita, User $usuario): void
    {
        $esteveLa = $usuario->technician !== null
            && (int) $usuario->technician->id === (int) $visita->technician_id;

        abort_unless(
            $esteveLa || $usuario->hasPermission('orders.approve'),
            403,
            'Quem encerra a visita é o técnico que esteve no local, ou quem responde pela escala.',
        );
    }

    /** @return array<string, array<int, string>> */
    private function regras(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'decimal:0,7', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'decimal:0,7', 'required_with:latitude'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'latitude.between' => 'A latitude lida está fora do mundo possível (-90 a 90).',
            'longitude.between' => 'A longitude lida está fora do mundo possível (-180 a 180).',
            'latitude.decimal' => 'A latitude não passa de sete casas decimais.',
            'longitude.decimal' => 'A longitude não passa de sete casas decimais.',
            'latitude.required_with' => 'Posição incompleta não é posição: envie a latitude junto com a longitude.',
            'longitude.required_with' => 'Posição incompleta não é posição: envie a longitude junto com a latitude.',
            'observacao.max' => 'O relato de campo tem de caber em 500 caracteres.',
        ];
    }

    /** O endereço da ordem pode não ter coordenada: aí não há vão a medir, e a tela diz isso. */
    private function resumoDaMedida(?float $medida, ServiceOrder $ordem): string
    {
        if ($medida === null) {
            return $ordem->latitude === null || $ordem->longitude === null
                ? ' — sem medida: o endereço da ordem não tem coordenada'
                : ' — sem posição lida no aparelho';
        }

        return sprintf(' (%s m do endereço)', Formatters::decimal($medida));
    }

    /** @return array<int, mixed> */
    private function linhaCsv(ServiceOrderCheckin $visita): array
    {
        $fora = $visita->foraDoRaio();

        return [
            $visita->serviceOrder?->number,
            $visita->serviceOrder?->client?->name,
            $visita->technician?->name,
            $visita->checkin_at,
            $visita->checkout_at,
            Formatters::duration($visita->duracaoMinutos()),
            $this->coordenadaCsv($visita->checkin_latitude, $visita->checkin_longitude),
            $visita->checkin_distance === null ? 'sem medida' : Formatters::decimal($visita->checkin_distance),
            match ($fora) {
                null => 'sem medida',
                true => 'fora do raio',
                false => 'dentro do raio',
            },
            $this->coordenadaCsv($visita->checkout_latitude, $visita->checkout_longitude),
            $visita->checkout_distance === null ? 'sem medida' : Formatters::decimal($visita->checkout_distance),
            StatusCatalog::label('checkin', $visita->status),
            $visita->observation,
        ];
    }

    private function coordenadaCsv(mixed $latitude, mixed $longitude): string
    {
        if ($latitude === null || $longitude === null) {
            return 'sem posição';
        }

        return Formatters::decimal($latitude, 6).', '.Formatters::decimal($longitude, 6);
    }

    /** @return array<int, string> */
    private function tecnicos(): array
    {
        return Technician::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int, string> */
    private function clientes(): array
    {
        return Client::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
