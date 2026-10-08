<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Compromisso da agenda de uma empresa: a janela reservada para uma visita, uma
 * instalação, uma coleta na base. Ele não é o trabalho — a ordem de serviço e o
 * chamado continuam donos do que acontece e do que se cobra. O que o compromisso
 * faz é colocar aquele trabalho num horário e num técnico da escala, e é por isso
 * que ele pode estar preso a um dos dois: quando está, o calendário mostra o
 * mesmo número que a ficha mostra, e não um recado digitado à parte.
 *
 * O estado é curto de propósito — agendado, concluído, cancelado. Não existe
 * trilha de passagens aqui porque não há conversa nem conta para fechar: o que
 * mudou de janela, de técnico ou de estado fica na trilha de auditoria, com autor
 * e hora, do mesmo jeito que fica no resto do domínio.
 */
class Appointment extends Model
{
    use Auditable, BelongsToCompany;

    /**
     * Fluxo do compromisso. Concluído é terminal: o que aconteceu já aconteceu, e
     * reabrir seria reescrever um dia que já passou. Cancelado volta a agendado
     * porque remarcar é exatamente o que uma agenda serve para fazer.
     *
     * @var array<string, array<int, string>>
     */
    public const FLUXO = [
        'scheduled' => ['completed', 'canceled'],
        'canceled' => ['scheduled'],
        'completed' => [],
    ];

    protected $fillable = [
        'company_id', 'technician_id', 'client_id', 'service_order_id', 'ticket_id', 'title',
        'description', 'type', 'status', 'starts_at', 'ends_at', 'all_day', 'location',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'all_day' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scopeBetween(Builder $q, $inicio, $fim): Builder
    {
        return $q->where('starts_at', '<=', $fim)->where('ends_at', '>=', $inicio);
    }

    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', 'scheduled');
    }

    public static function alcanceRestrito(User $usuario): bool
    {
        return $usuario->client_id !== null || $usuario->technician !== null;
    }

    /**
     * Quem enxerga este compromisso. É o mesmo alcance das ordens e dos chamados,
     * e pela mesma razão: a agenda do técnico não pode mostrar a janela de outro
     * técnico, e a conta de cliente não pode ler a escala da empresa. O compromisso
     * também é alcançável por aquilo que ele prende — se a ordem agendada é do
     * usuário, a janela dela é dele, mesmo sem técnico marcado.
     */
    public function scopeVisiveisPara(Builder $q, User $usuario): Builder
    {
        if ($usuario->client_id !== null || $usuario->technician !== null) {
            $ordens = ServiceOrder::query()->visiveisPara($usuario)->select('id');
            $chamados = Ticket::query()->visiveisPara($usuario)->select('id');

            return $q->where(fn (Builder $lado) => $lado
                ->when($usuario->technician, fn (Builder $m) => $m->where('appointments.technician_id', $usuario->technician->id))
                ->when($usuario->client_id, fn (Builder $m) => $m->where('appointments.client_id', $usuario->client_id))
                ->orWhereIn('appointments.service_order_id', $ordens)
                ->orWhereIn('appointments.ticket_id', $chamados));
        }

        return $q;
    }

    public function podeMudarPara(string $novo): bool
    {
        return $novo !== $this->status && in_array($novo, self::FLUXO[$this->status] ?? [], true);
    }

    /** Concluído trava a edição: a janela já passou e o que foi feito está contado. */
    public function estaTravado(): bool
    {
        return $this->status === 'completed';
    }

    /** Duração em minutos, lida da própria janela gravada — não de um campo digitado. */
    public function minutos(): int
    {
        return (int) ($this->starts_at && $this->ends_at
            ? $this->starts_at->diffInMinutes($this->ends_at)
            : 0);
    }
}
