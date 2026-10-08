<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialRecord extends Model
{
    use BelongsToCompany, SoftDeletes;

    public const REVENUE = 'revenue';

    public const EXPENSE = 'expense';

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
        return $q->where('status', 'pending');
    }

    public function scopePaid(Builder $q): Builder
    {
        return $q->where('status', 'paid');
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->where('status', 'pending')
            ->whereDate('due_date', '<', now()->toDateString());
    }

    public function scopeOccurredBetween(Builder $q, $inicio, $fim): Builder
    {
        // occurred_at é a data do fato; due_date é o vencimento previsto, então usar
        // due_date contaria receita realizada no período errado.
        return $q->whereBetween('occurred_at', [$inicio, $fim]);
    }
}
