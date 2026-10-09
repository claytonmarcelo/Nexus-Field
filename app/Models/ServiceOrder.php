<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ServiceOrder extends Model
{
    use Auditable, BelongsToCompany;

    /**
     * O número nasce do ano corrente e de uma sequência por empresa, e é a
     * identidade impressa da ordem: `OS-2026-0031`. A coluna tem índice único
     * por empresa, então a geração é feita sob trava dentro da transação.
     */
    public const PREFIXO_NUMERO = 'OS-';

    /**
     * Fluxo de estados: de para onde cada estado leva. Concluída e cancelada são
     * terminais — o caminho de volta não é reabrir a ordem, é abrir outra, porque
     * o que já foi cobrado e medido em campo não se reescreve.
     *
     * @var array<string, array<int, string>>
     */
    public const FLUXO = [
        'draft' => ['open', 'canceled'],
        'open' => ['in_progress', 'on_hold', 'canceled'],
        'in_progress' => ['on_hold', 'completed', 'canceled'],
        'on_hold' => ['in_progress', 'canceled'],
        'completed' => [],
        'canceled' => [],
    ];

    /** Estados que a tela de cadastro aceita no nascimento da ordem. */
    public const ESTADOS_INICIAIS = ['draft', 'open'];

    /** Estados que pedem a decisão de quem aprova, e não só de quem executa. */
    public const ESTADOS_APROVADOS = ['open', 'canceled'];

    protected $fillable = [
        'company_id', 'client_id', 'service_id', 'technician_id', 'team_id', 'number', 'title',
        'description', 'priority', 'status', 'scheduled_starts_at', 'scheduled_ends_at',
        'started_at', 'completed_at', 'cancelled_at', 'execution_notes', 'cancellation_reason',
        'discount', 'street', 'number_address', 'complement', 'neighborhood', 'city', 'state',
        'zip_code', 'latitude', 'longitude',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_starts_at' => 'datetime',
            'scheduled_ends_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'discount' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceOrderItem::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ServiceOrderAssignment::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ServiceOrderStatusHistory::class)->latest('created_at');
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(ServiceOrderCheckin::class);
    }

    public function financialRecords(): HasMany
    {
        return $this->hasMany(FinancialRecord::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'in_progress', 'on_hold']);
    }

    public function scopeInProgress(Builder $q): Builder
    {
        return $q->where('status', 'in_progress');
    }

    public function scopeCompleted(Builder $q): Builder
    {
        return $q->where('status', 'completed');
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'in_progress'])
            ->whereNotNull('scheduled_ends_at')
            ->where('scheduled_ends_at', '<', now());
    }

    public function scopeScheduledBetween(Builder $q, $inicio, $fim): Builder
    {
        return $q->whereBetween('scheduled_starts_at', [$inicio, $fim]);
    }

    public static function alcanceRestrito(User $usuario): bool
    {
        return $usuario->client_id !== null || $usuario->technician !== null;
    }

    /**
     * Quem enxerga o quê dentro da empresa. O técnico vê as ordens dele — no
     * campo ele é apontado tanto pela coluna da ordem quanto pelo quadro de
     * comissão — e a conta de cliente vê só a carteira dela. Quem não tem nenhum
     * dos dois vínculos é o escritório: vê a operação inteira da empresa.
     *
     * A conta de cliente é resolvida pela coluna que já veio com o usuário, então
     * ela vem antes: consultar a ficha de técnico para quem é cliente seria uma
     * query a mais no painel, e o painel do cliente roda a cada entrada.
     */
    public function scopeVisiveisPara(Builder $q, User $usuario): Builder
    {
        if ($usuario->client_id !== null) {
            return $q->where('client_id', $usuario->client_id);
        }

        if ($tecnico = $usuario->technician) {
            return $q->where(fn (Builder $lado) => $lado
                ->where('technician_id', $tecnico->id)
                ->orWhereHas('assignments', fn (Builder $comissao) => $comissao->where('technician_id', $tecnico->id)));
        }

        return $q;
    }

    /**
     * Subtotal e total dos itens calculados no MySQL. Um `join` com `groupBy`
     * substituiria o SELECT de service_orders e quebraria a paginação da
     * listagem; a subconsulta correlacionada deixa a linha da ordem inteira.
     */
    public function scopeWithTotals(Builder $q): Builder
    {
        return $q->select('service_orders.*')
            ->selectSub(static::somaBrutaQuery(), 'gross_total')
            ->selectSub(static::somaLiquidaQuery(), 'items_total');
    }

    /**
     * Totais da tela. A listagem traz os dois alias do MySQL; a ficha carrega os
     * itens porque a tabela deles é o próprio corpo da página. São duas origens
     * do mesmo número, nunca dois números.
     *
     * @return array{bruto: float, liquido: float, desconto: float, total: float}
     */
    public function totais(): array
    {
        if ($this->relationLoaded('items')) {
            $bruto = $this->items->sum(fn (ServiceOrderItem $item) => $item->subtotal());
            $liquido = $this->items->sum(fn (ServiceOrderItem $item) => $item->total());
        } else {
            $bruto = (float) ($this->attributes['gross_total'] ?? 0);
            $liquido = (float) ($this->attributes['items_total'] ?? 0);
        }

        $daOrdem = (float) $this->discount;

        return [
            'bruto' => $bruto,
            'liquido' => $liquido,
            // O desconto tem duas origens legítimas: o item que já sai com valor
            // de fora e o desconto jogado na ordem inteira.
            'desconto' => round($bruto - $liquido + $daOrdem, 2),
            'total' => round($liquido - $daOrdem, 2),
        ];
    }

    public function podeMudarPara(string $novo): bool
    {
        return $novo !== $this->status && in_array($novo, self::FLUXO[$this->status] ?? [], true);
    }

    /**
     * Estado que fecha a conta: depois dele a linha não se reescreve mais, nem
     * item nem quadro de comissão. O que estiver errado vira outra ordem, porque
     * o que foi cobrado e medido em campo é histórico do cliente.
     */
    public function estaEncerrada(): bool
    {
        return in_array($this->status, ['completed', 'canceled'], true);
    }

    /**
     * Aplicar um estado é mais que trocar a coluna: o carimbo certo tem de entrar
     * na linha e a passagem tem de ficar registrada com quem fez e por quê. É o
     * único caminho que escreve `status` depois da ordem nascida — a tela de
     * edição não oferece o campo.
     */
    public function mudarStatus(string $novo, User $autor, ?string $nota = null): void
    {
        if (! $this->podeMudarPara($novo)) {
            throw new \InvalidArgumentException("A ordem {$this->number} não pode ir de {$this->status} para {$novo}.");
        }

        $antes = $this->status;
        $agora = now();

        if (in_array($novo, ['in_progress', 'completed'], true) && $this->started_at === null) {
            $this->started_at = $agora;
        }

        if ($novo === 'completed') {
            $this->completed_at = $agora;
        }

        if ($novo === 'canceled') {
            $this->cancelled_at = $agora;
            $this->cancellation_reason = $nota;
        }

        $this->status = $novo;
        $this->save();

        ServiceOrderStatusHistory::query()->create([
            'service_order_id' => $this->id,
            'user_id' => $autor->id,
            'from_status' => $antes,
            'to_status' => $novo,
            'note' => $nota,
            'created_at' => $agora,
        ]);
    }

    /**
     * O próximo número da empresa: pega o maior número do ano dentro de uma
     * transação com trava, para duas ordens abertas no mesmo minuto não pedirem
     * a mesma sequência. O índice único por empresa é a rede de trás.
     */
    public static function proximoNumero(): string
    {
        $prefixo = self::PREFIXO_NUMERO.now()->format('Y').'-';

        return DB::transaction(function () use ($prefixo): string {
            $maior = static::query()
                ->where('number', 'like', $prefixo.'%')
                ->lockForUpdate()
                ->max('number');

            $sequencia = $maior === null ? 0 : (int) substr((string) $maior, strlen($prefixo));

            return $prefixo.str_pad(strval($sequencia + 1), 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * O total de uma ordem escrito em SQL: o líquido dos itens menos o desconto da
     * ordem, que é exatamente a conta de `totais()['total']`. O relatório de
     * operação precisa somar este valor por técnico dentro de um `groupBy`, e a
     * subconsulta correlacionada é o que evita o fã-out: juntar a tabela de itens
     * multiplicaria as linhas da ordem e o tempo médio de execução sairia inflado.
     * Sem binding, porque a expressão é só colunas da casa.
     */
    public static function totalPorOrdemSql(): string
    {
        return '('.static::somaDeItens('quantity * unit_price - discount')->toSql().')'
            .' - coalesce(service_orders.discount, 0)';
    }

    private static function somaBrutaQuery(): QueryBuilder
    {
        return static::somaDeItens('quantity * unit_price');
    }

    private static function somaLiquidaQuery(): QueryBuilder
    {
        return static::somaDeItens('quantity * unit_price - discount');
    }

    private static function somaDeItens(string $expressao): QueryBuilder
    {
        return DB::table('service_order_items')
            ->selectRaw('coalesce(sum('.$expressao.'), 0)')
            ->whereColumn('service_order_items.service_order_id', 'service_orders.id');
    }
}
