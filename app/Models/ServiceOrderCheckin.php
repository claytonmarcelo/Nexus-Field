<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A passagem do técnico pelo endereço da ordem: a chegada com o que o aparelho
 * respondeu de posição, e a saída quando o trabalho terminou.
 *
 * O carimbo é do servidor, nunca do navegador. Uma hora lida do relógio do
 * celular daria ao interessado o poder de escolher o horário em que diz ter
 * chegado, e a diferença entre "cheguei às 14h" e "cheguei quando o servidor
 * registrou" é exatamente o que a auditoria precisa responder. A coordenada, ao
 * contrário, é o que o aparelho viu: ela entra como veio, e a distância até o
 * endereço é calculada aqui do lado de cá.
 *
 * Sem posição a visita continua válida. GPS falha em prédio, em subsolo e dentro
 * de carro; recusar o check-in por isso ensinaria o técnico a inventar coordenada
 * em vez de registrar o que aconteceu. O que não existe é medida: a linha fica
 * marcada como sem posição, e é isso que ela diz.
 */
class ServiceOrderCheckin extends Model
{
    use Auditable, BelongsToCompany;

    /**
     * Tolerância padrão entre a posição lida e o endereço da ordem. É o número da
     * casa quando a empresa não escolheu outro: 250 metros cabem um quarteirão de
     * loja com fundo de lote e não cabem dois bairros.
     */
    public const RAIO_PADRAO_METROS = 250.0;

    /** A chave com que a tela de configurações da empresa guarda o raio escolhido. */
    public const CHAVE_RAIO = 'checkin_raio';

    protected $fillable = [
        'company_id', 'service_order_id', 'technician_id', 'checkin_at', 'checkin_latitude',
        'checkin_longitude', 'checkin_distance', 'checkout_at', 'checkout_latitude',
        'checkout_longitude', 'checkout_distance', 'status', 'observation',
    ];

    protected function casts(): array
    {
        return [
            'checkin_at' => 'datetime',
            'checkin_latitude' => 'decimal:7',
            'checkin_longitude' => 'decimal:7',
            'checkin_distance' => 'decimal:2',
            'checkout_at' => 'datetime',
            'checkout_latitude' => 'decimal:7',
            'checkout_longitude' => 'decimal:7',
            'checkout_distance' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNull('checkout_at');
    }

    public function scopeClosed(Builder $q): Builder
    {
        return $q->whereNotNull('checkout_at');
    }

    public function scopeToday(Builder $q): Builder
    {
        return $q->whereDate('checkin_at', now()->toDateString());
    }

    /**
     * Visitas sem nenhuma coordenada nos dois carimbos — a lista do escritório tem
     * de poder perguntar por elas, porque "o campo não mediu" é um fato que se
     * gerencia, não um detalhe de rodapé.
     */
    public function scopeSemPosicao(Builder $q): Builder
    {
        return $q->whereNull('checkin_latitude')->whereNull('checkout_latitude');
    }

    public function scopeEntre(Builder $q, $inicio, $fim): Builder
    {
        return $q->whereBetween('checkin_at', [$inicio, $fim]);
    }

    public static function alcanceRestrito(User $usuario): bool
    {
        return $usuario->client_id !== null || $usuario->technician !== null;
    }

    /**
     * Quem enxerga esta passagem. É o alcance da casa aplicado ao check-in: o
     * técnico lê a própria escala de campo, a conta de cliente lê o que foi medido
     * na carteira dela, e o escritório lê a empresa. A passagem não tem vínculo
     * direto com o cliente, então a conta de cliente entra pela ordem — que é o
     * único lugar de onde ela vem.
     */
    public function scopeVisiveisPara(Builder $q, User $usuario): Builder
    {
        if ($usuario->client_id !== null) {
            return $q->whereHas('serviceOrder', fn (Builder $ordem) => $ordem->where('client_id', $usuario->client_id));
        }

        if ($tecnico = $usuario->technician) {
            return $q->where('technician_id', $tecnico->id);
        }

        return $q;
    }

    public function estaAberto(): bool
    {
        return $this->checkout_at === null;
    }

    /** Tempo no local, do carimbo de entrada ao de saída. Visita aberta não tem duração: ainda está acontecendo. */
    public function duracaoMinutos(): ?int
    {
        if ($this->checkout_at === null) {
            return null;
        }

        return (int) $this->checkin_at->diffInMinutes($this->checkout_at);
    }

    /**
     * A chegada ficou fora do raio que a empresa aceita. Null é "não medido", e o
     * desenho da tela trata as três respostas — dentro, fora, sem medida — com
     * rótulos diferentes, porque a terceira não é a segunda disfarçada.
     */
    public function foraDoRaio(): ?bool
    {
        if ($this->checkin_distance === null) {
            return null;
        }

        return (float) $this->checkin_distance > static::raioAceito();
    }

    public static function raioAceito(): float
    {
        $escolhido = CompanySetting::valueFor(self::CHAVE_RAIO);

        return is_numeric($escolhido) && (float) $escolhido > 0
            ? (float) $escolhido
            : self::RAIO_PADRAO_METROS;
    }
}
