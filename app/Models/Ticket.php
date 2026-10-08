<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Ticket extends Model
{
    use Auditable, BelongsToCompany;

    /**
     * O protocolo é a identidade impressa do chamado: `CH-2026-0007`, sequência do
     * ano por empresa. Como o índice é único por empresa, a geração acontece sob
     * trava dentro de uma transação.
     */
    public const PREFIXO_PROTOCOLO = 'CH-';

    /**
     * Fluxo de estados do chamado. Diferente da ordem, que não volta de concluída:
     * resolvido que o cliente relata quebrado de novo reabre, porque o chamado é a
     * voz do cliente, não a conta cobrada. Fechado é o único terminal — depois dele
     * a conversa para e o que existir de novo é outro protocolo.
     *
     * @var array<string, array<int, string>>
     */
    public const FLUXO = [
        'open' => ['in_progress', 'waiting', 'closed'],
        'in_progress' => ['waiting', 'resolved', 'closed'],
        'waiting' => ['in_progress', 'resolved', 'closed'],
        'resolved' => ['in_progress', 'closed'],
        'closed' => [],
    ];

    /** Estados que ainda esperam alguém — é neles que o prazo corre. */
    public const EM_ANDAMENTO = ['open', 'in_progress', 'waiting'];

    /** Estados que pedem a decisão de quem encerra, não só de quem atende. */
    public const ESTADOS_APROVADOS = ['resolved', 'closed'];

    /**
     * Horas até a resolução, por prioridade. É regra do módulo, escrita aqui uma
     * vez: o que o banco guarda é `opened_at` e a prioridade, e o prazo é calculado
     * dos dois. Nenhuma tela inventa data de vencimento.
     */
    public const PRAZO_HORAS = [
        'urgent' => 4,
        'high' => 8,
        'normal' => 24,
        'low' => 72,
    ];

    /** Prioridade fora da tabela (dado importado) recebe o prazo da normal. */
    public const PRAZO_PADRAO = 24;

    protected $fillable = [
        'company_id', 'client_id', 'service_order_id', 'responsible_user_id', 'technician_id',
        'protocol', 'subject', 'description', 'category', 'priority', 'status', 'opened_at',
        'resolved_at', 'closed_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class)->latest('created_at');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', self::EM_ANDAMENTO);
    }

    public function scopeUrgent(Builder $q): Builder
    {
        return $q->whereIn('priority', ['high', 'urgent']);
    }

    public function scopeResolvedBetween(Builder $q, $inicio, $fim): Builder
    {
        return $q->whereBetween('resolved_at', [$inicio, $fim]);
    }

    /**
     * Chamados cujo prazo da própria prioridade venceu e ninguém encerrou. A
     * igualdade acontece no MySQL, com o `case` montado da constante: a listagem
     * paginada e a exportação filtram exatamente a mesma linha que a tela mostra.
     */
    public function scopeAtrasados(Builder $q): Builder
    {
        return $q->whereIn('status', self::EM_ANDAMENTO)
            ->whereRaw('opened_at < now() - interval ('.static::prazoSql().') hour');
    }

    public static function alcanceRestrito(User $usuario): bool
    {
        return $usuario->client_id !== null || $usuario->technician !== null;
    }

    /**
     * Alcance do chamado: a conta de cliente vê a carteira dela, o técnico vê o que
     * é dele — apontado na coluna ou nomeado responsável interno — e o escritório vê
     * a empresa. A conta de cliente vem antes porque a coluna já veio com o
     * usuário, então ela custa zero queries.
     */
    public function scopeVisiveisPara(Builder $q, User $usuario): Builder
    {
        if ($usuario->client_id !== null) {
            return $q->where('client_id', $usuario->client_id);
        }

        if ($tecnico = $usuario->technician) {
            return $q->where(fn (Builder $lado) => $lado
                ->where('technician_id', $tecnico->id)
                ->orWhere('responsible_user_id', $usuario->id));
        }

        return $q;
    }

    public function podeMudarPara(string $novo): bool
    {
        return $novo !== $this->status && in_array($novo, self::FLUXO[$this->status] ?? [], true);
    }

    /** Chamado fechado não aceita conversa nem estado novo. */
    public function estaEncerrado(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Data-limite que a prioridade deste chamado impõe, contada da abertura. Não é
     * coluna: é a regra da classe aplicada ao carimbo que o banco já tem.
     */
    public function prazoResolucao(): ?Carbon
    {
        if ($this->opened_at === null) {
            return null;
        }

        return $this->opened_at->copy()->addHours(
            self::PRAZO_HORAS[$this->priority] ?? self::PRAZO_PADRAO
        );
    }

    public function prazoVencido(): bool
    {
        $prazo = $this->prazoResolucao();

        return $prazo !== null && in_array($this->status, self::EM_ANDAMENTO, true) && $prazo->isPast();
    }

    /**
     * Aplicar um estado põe o carimbo do momento, grava a passagem com quem fez e
     * guarda a nota do passo. É o único caminho que escreve `status` depois que o
     * chamado nasceu — a tela de edição não oferece o campo.
     */
    public function mudarStatus(string $novo, User $autor, ?string $nota = null): void
    {
        if (! $this->podeMudarPara($novo)) {
            throw new \InvalidArgumentException("O chamado {$this->protocol} não pode ir de {$this->status} para {$novo}.");
        }

        $antes = $this->status;
        $agora = now();

        if ($novo === 'resolved') {
            $this->resolved_at = $agora;
            $this->resolution_note = $nota;
        }

        if ($novo === 'closed') {
            $this->closed_at = $agora;
        }

        // Reaberto volta a correr o prazo: o carimbo de resolução sai da linha, e
        // quando ele existiu fica registrado na passagem de estado.
        if ($novo === 'in_progress') {
            $this->resolved_at = null;
            $this->closed_at = null;
        }

        $this->status = $novo;
        $this->save();

        TicketStatusHistory::query()->create([
            'ticket_id' => $this->id,
            'user_id' => $autor->id,
            'from_status' => $antes,
            'to_status' => $novo,
            'note' => $nota,
            'created_at' => $agora,
        ]);
    }

    /** @see ServiceOrder::proximoNumero() — mesma mecânica, outro documento. */
    public static function proximoProtocolo(): string
    {
        $prefixo = self::PREFIXO_PROTOCOLO.now()->format('Y').'-';

        return DB::transaction(function () use ($prefixo): string {
            $maior = static::query()
                ->where('protocol', 'like', $prefixo.'%')
                ->lockForUpdate()
                ->max('protocol');

            $sequencia = $maior === null ? 0 : (int) substr((string) $maior, strlen($prefixo));

            return $prefixo.str_pad(strval($sequencia + 1), 4, '0', STR_PAD_LEFT);
        });
    }

    private static function prazoSql(): string
    {
        $casos = collect(self::PRAZO_HORAS)
            ->map(fn (int $horas, string $prioridade) => "when priority = '".$prioridade."' then ".$horas)
            ->implode(' ');

        return 'case '.$casos.' else '.self::PRAZO_PADRAO.' end';
    }
}
