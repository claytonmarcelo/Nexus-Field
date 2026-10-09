<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Formatters;
use App\Support\StatusCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Uma conta que a empresa tem a receber ou a pagar.
 *
 * O estado do lançamento não é campo de formulário: é consequência do que entrou
 * em `payments`. Alguém digitar "Pago" num select é a receita do cadastro
 * desconfiado — a conta aparece quitada sem que nenhum dinheiro tenha sido
 * registrado, e o painel soma um dinheiro que não existe. Aqui o que decide
 * `status` é a soma dos pagamentos contra o valor, calculada pelo servidor dentro
 * da mesma transação que grava o pagamento (`recalcularEstado()`), com a linha
 * travada.
 *
 * `canceled` é a exceção, e por um motivo: cancelamento não é conta quitada, é
 * conta que nunca passou a existir para o caixa. Ele só acontece sem pagamento
 * registrado, justamente para não apagar dinheiro que entrou.
 *
 * `occurred_at` também não é escolha de quem digita: é a data do último
 * pagamento, e por isso lançamento sem pagamento não tem data de ocorrência — não
 * aconteceu. O `due_date` continua sendo previsto, e é dele que sai o "vencido".
 */
class FinancialRecord extends Model
{
    use Auditable, BelongsToCompany, SoftDeletes;

    public const REVENUE = 'revenue';

    public const EXPENSE = 'expense';

    public const PENDING = 'pending';

    public const PARTIAL = 'partially_paid';

    public const PAID = 'paid';

    public const CANCELED = 'canceled';

    /**
     * A soma paga em SQL cru, uma única vez: a coluna da listagem, o rodapé de
     * totais e o `valorPago()` da ficha saem da mesma expressão, para o saldo que a
     * tabela mostra não ser parecido com o da ficha — ser o mesmo. Sem binding, o
     * que evita concatenação de valor de usuário no SQL.
     */
    private const PAGADA_SQL = 'coalesce((select sum(payments.amount) from payments '
        .'where payments.financial_record_id = financial_records.id), 0)';

    /**
     * Categorias fechadas por tipo. O legado deixava digitar categoria livre, e em
     * dois anos o relatório tinha "Peças", "pecas", "pç" e "Mão de obra + peças"
     * como quatro categorias diferentes. A coluna é um vocabulário, não um campo de
     * nota: sem ele, o agrupamento do relatório (fase 18) soma linhas que ninguém
     * consegue comparar.
     *
     * @var array<string, array<string, string>>
     */
    public const CATEGORIAS = [
        self::REVENUE => [
            'mao_de_obra_e_pecas' => 'Mão de obra e peças',
            'contrato_mensal' => 'Contrato mensal',
            'visita_tecnica' => 'Visita técnica avulsa',
            'emergencia' => 'Atendimento de emergência',
            'outra_receita' => 'Outra receita',
        ],
        self::EXPENSE => [
            'estoque' => 'Compra de estoque',
            'deslocamento' => 'Deslocamento e combustível',
            'instalacao' => 'Instalação e base',
            'pessoal' => 'Pessoal',
            'impostos' => 'Impostos e taxas',
            'outra_despesa' => 'Outra despesa',
        ],
    ];

    protected $fillable = [
        'company_id', 'client_id', 'service_order_id', 'type', 'category', 'description',
        'amount', 'due_date', 'occurred_at', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'occurred_at' => 'date',
            'amount' => 'decimal:2',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * A expressão da carteira, pública para o relatório de financeiro somar o que
     * já mudou de mão sem escrever uma segunda régua: listagem, ficha, rodapé e
     * relatório somam o mesmo SQL. Sem binding, então não há valor de usuário
     * entrando na concatenação.
     */
    public static function pagoSql(): string
    {
        return self::PAGADA_SQL;
    }

    /** @return array<string, string> categorias do tipo, ou o catálogo inteiro se o tipo não foi escolhido */
    public static function categorias(?string $tipo = null): array
    {
        if ($tipo !== null && isset(self::CATEGORIAS[$tipo])) {
            return self::CATEGORIAS[$tipo];
        }

        return $tipo === null
            ? self::CATEGORIAS[self::REVENUE] + self::CATEGORIAS[self::EXPENSE]
            : [];
    }

    /** Categoria fora do catálogo (dado importado, lançamento antigo) se descreve sozinha em vez de sumir da tela. */
    public static function rotuloCategoria(?string $categoria): string
    {
        foreach (self::CATEGORIAS as $linhas) {
            if (isset($linhas[$categoria])) {
                return $linhas[$categoria];
            }
        }

        return $categoria === null || $categoria === ''
            ? 'Sem categoria'
            : Str::ucfirst(str_replace('_', ' ', $categoria));
    }

    public function eReceita(): bool
    {
        return $this->type === self::REVENUE;
    }

    /**
     * O valor que já mudou de mão. Três origens, a mesma soma: o alias da subquery
     * (listagem e exportação, sem uma consulta por linha), a relação carregada
     * (ficha) e o banco (dentro da transação). O saldo que a tabela mostra é o da
     * ficha porque os três somam a mesma expressão.
     */
    public function valorPago(): float
    {
        if ($this->hasAttribute('pago_total')) {
            return round((float) $this->attributes['pago_total'], 2);
        }

        if ($this->relationLoaded('payments')) {
            return round((float) $this->payments->sum('amount'), 2);
        }

        return $this->pagoNoBanco();
    }

    /** O que falta. Zero numa conta quitada; negativo não existe, porque pagamento acima do saldo é recusado. */
    public function saldo(): float
    {
        return max(0, round((float) $this->amount - $this->valorPago(), 2));
    }

    public function temPagamento(): bool
    {
        if ($this->relationLoaded('payments')) {
            return $this->payments->isNotEmpty();
        }

        return $this->payments()->exists();
    }

    /** Vencida é relação entre o previsto e hoje, não uma coluna: o tempo passa sem que alguém edite a linha. */
    public function estaVencida(): bool
    {
        return in_array($this->status, [self::PENDING, self::PARTIAL], true)
            && $this->due_date !== null
            && $this->due_date->lt(Carbon::today());
    }

    public function diasEmAtraso(): int
    {
        if (! $this->estaVencida()) {
            return 0;
        }

        // `diffInDays` devolve float e sinal em Carbon 3: aqui interessa o número de
        // dias corridos, contado do vencimento para hoje.
        return (int) round($this->due_date->startOfDay()->diffInDays(Carbon::today(), false));
    }

    /**
     * O rótulo que a pessoa lê. Recebido e pago são a mesma conta em direções
     * contrárias, e dizer "Pago" numa receita que entrou dinheiro é o tipo de
     * frase que faz alguém conferir o extrato à toa.
     */
    public function rotuloEstado(): string
    {
        return match ($this->status) {
            self::CANCELED => 'Cancelado',
            self::PAID => $this->eReceita() ? 'Recebido' : 'Pago',
            self::PARTIAL => $this->eReceita() ? 'Recebido em parte' : 'Pago em parte',
            default => $this->estaVencida()
                ? 'Vencido'
                : ($this->eReceita() ? 'A receber' : 'A pagar'),
        };
    }

    public function badgeEstado(): string
    {
        $tom = $this->estaVencida() ? 'canceled' : StatusCatalog::tone('financial', $this->status);

        return 'nf-status nf-status-'.$tom;
    }

    /**
     * Deriva o estado da soma dos pagamentos. É o único caminho que escreve
     * `status` depois que o lançamento nasce, e quem chama tem de estar dentro da
     * transação com a linha travada: dois pagamentos registrados no mesmo segundo,
     * cada um somando o que viu na tela, é o pagamento que fecha uma conta com
     * metade do valor.
     *
     * Cancelamento é decisão, não conta quitada: linha cancelada tem o estado
     * preservado, e a tela não oferece pagamento nela.
     */
    public function recalcularEstado(): string
    {
        if ($this->status === self::CANCELED) {
            return $this->status;
        }

        $valor = (float) $this->amount;
        $caixa = $this->linhaDeCaixa();
        $pago = round((float) $caixa->pago, 2);

        $novo = match (true) {
            $pago <= 0 => self::PENDING,
            $pago + 0.005 < $valor => self::PARTIAL,
            default => self::PAID,
        };

        $this->status = $novo;
        $this->occurred_at = $pago > 0 && $caixa->ultimo != null ? Carbon::parse($caixa->ultimo) : null;
        $this->save();

        return $novo;
    }

    /**
     * Soma e data lidas do banco numa passada só, dentro da transação com a linha
     * travada. Uma consulta porque as duas coisas têm de nascer do mesmo instante:
     * somar os pagamentos de agora e carimbar a data de antes é o estado parcial
     * que vira quitado com a data errada.
     */
    private function linhaDeCaixa(): object
    {
        return Payment::query()
            ->where('financial_record_id', $this->id)
            ->selectRaw('coalesce(sum(amount), 0) pago, max(paid_at) ultimo')
            ->first();
    }

    /** O pago lido do banco agora, sem atalho de relação nem alias: é o número que decide o estado. */
    public function pagoNoBanco(): float
    {
        return round((float) $this->linhaDeCaixa()->pago, 2);
    }

    public function scopeRevenue(Builder $q): Builder
    {
        return $q->where('type', static::REVENUE);
    }

    public function scopeExpense(Builder $q): Builder
    {
        return $q->where('type', static::EXPENSE);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::PENDING);
    }

    public function scopePaid(Builder $q): Builder
    {
        return $q->where('status', self::PAID);
    }

    /**
     * Conta que ainda deve dinheiro: sem pagamento, com pagamento parcial, e não
     * cancelada. É daqui que saem "a receber" e "a pagar" — somar só `pending`
     * esqueceria a conta meio paga, que é justamente a que cobra atenção.
     */
    public function scopeEmAberto(Builder $q): Builder
    {
        return $q->whereIn('status', [self::PENDING, self::PARTIAL]);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->emAberto()->whereDate('due_date', '<', Carbon::today());
    }

    public function scopeOccurredBetween(Builder $q, $inicio, $fim): Builder
    {
        // occurred_at é a data do fato; due_date é o vencimento previsto, então usar
        // due_date contaria receita realizada no período errado.
        return $q->whereBetween('occurred_at', [$inicio, $fim]);
    }

    /**
     * O pago de cada linha, somado no MySQL ao lado do valor. A listagem e a
     * exportação leem daqui; a ficha usa `valorPago()`. Dois caminhos, a mesma
     * soma — e é por isso que o saldo da tabela bate com o da ficha.
     */
    public function scopeComPagado(Builder $q): Builder
    {
        return $q->select('financial_records.*')
            ->addSelect(DB::raw(self::PAGADA_SQL.' as pago_total'));
    }

    /**
     * Totais de um conjunto já filtrado, na mesma consulta da tela: o número do
     * rodapé é a soma do que a pessoa está olhando, não da empresa inteira. São
     * duas agregações porque "o que venceu" obedece a um recorte a mais — conta em
     * aberto com vencimento no passado — e uma coluna derivada não entra no `sum`
     * do MySQL.
     *
     * @param  Builder<FinancialRecord>  $consulta
     * @return array{registros: int, bruto: float, pago: float, em_aberto: float, vencido: int, vencido_valor: float}
     */
    public static function totais(Builder $consulta): array
    {
        $linha = $consulta->clone()
            ->reorder()
            ->selectRaw('count(*) registros, coalesce(sum(financial_records.amount), 0) bruto, '
                .'coalesce(sum('.self::PAGADA_SQL.'), 0) pago')
            ->first();

        $bruto = round((float) ($linha->bruto ?? 0), 2);
        $pago = round((float) ($linha->pago ?? 0), 2);

        $vencida = $consulta->clone()
            ->reorder()
            ->emAberto()
            ->whereDate('due_date', '<', Carbon::today())
            ->selectRaw('count(*) q, coalesce(sum(financial_records.amount), 0) bruto, '
                .'coalesce(sum('.self::PAGADA_SQL.'), 0) pago')
            ->first();

        return [
            'registros' => (int) ($linha->registros ?? 0),
            'bruto' => $bruto,
            'pago' => $pago,
            'em_aberto' => round(max(0, $bruto - $pago), 2),
            'vencido' => (int) ($vencida->q ?? 0),
            'vencido_valor' => round(max(0, (float) ($vencida->bruto ?? 0) - (float) ($vencida->pago ?? 0)), 2),
        ];
    }

    protected static function resumoAuditoria(Model $modelo): string
    {
        return sprintf(
            'lançamento %s de %s',
            Str::limit(trim(strval($modelo->description)), 60, '') ?: '(sem descrição)',
            Formatters::money($modelo->amount),
        );
    }
}
