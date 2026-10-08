<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Vocabulário de estados e prioridades do domínio, com o rótulo em português e o
 * tom visual que o design system já define (`.nf-status-*`). Migration, seeder,
 * painel e futuras telas de listagem leem daqui, para a mesma coluna `status` não
 * aparecer com nomes diferentes em duas telas.
 *
 * O tom cai na classe `nf-status-*`; valores fora da lista (um status gravado
 * antes de existir cadastro, ou dado importado) descrevem-se sozinhos em vez de
 * sumir da tela.
 */
class StatusCatalog
{
    /** @var array<string, array<string, array{0: string, 1: string}>> */
    private const VALUES = [
        'order' => [
            'draft' => ['Rascunho', 'draft'],
            'open' => ['Aberta', 'open'],
            'in_progress' => ['Em execução', 'progress'],
            'on_hold' => ['Em espera', 'waiting'],
            'completed' => ['Concluída', 'done'],
            'canceled' => ['Cancelada', 'canceled'],
        ],
        'ticket' => [
            'open' => ['Aberto', 'open'],
            'in_progress' => ['Em atendimento', 'progress'],
            'waiting' => ['Aguardando cliente', 'waiting'],
            'resolved' => ['Resolvido', 'done'],
            'closed' => ['Fechado', 'draft'],
        ],
        'priority' => [
            'low' => ['Baixa', 'draft'],
            'normal' => ['Normal', 'open'],
            'high' => ['Alta', 'progress'],
            'urgent' => ['Urgente', 'canceled'],
        ],
        'technician' => [
            'available' => ['Disponível', 'done'],
            'busy' => ['Em campo', 'progress'],
            'off' => ['Fora de escala', 'draft'],
            'inactive' => ['Inativo', 'canceled'],
        ],
        'financial' => [
            'pending' => ['A receber', 'open'],
            'paid' => ['Pago', 'done'],
            'canceled' => ['Cancelado', 'canceled'],
            // Derivado da relação due_date com hoje; não é coluna da tabela.
            'overdue' => ['Vencido', 'canceled'],
        ],
        'client' => [
            'active' => ['Ativo', 'done'],
            'inactive' => ['Inativo', 'draft'],
        ],
    ];

    public static function label(string $grupo, ?string $valor): string
    {
        $entrada = self::VALUES[$grupo][$valor ?? ''] ?? null;

        return $entrada[0] ?? ($valor === null || $valor === ''
            ? 'Sem estado'
            : Str::ucfirst(str_replace('_', ' ', $valor)));
    }

    public static function tone(string $grupo, ?string $valor): string
    {
        return (self::VALUES[$grupo][$valor ?? ''] ?? null)[1] ?? 'draft';
    }

    public static function badge(string $grupo, ?string $valor): string
    {
        return 'nf-status nf-status-'.static::tone($grupo, $valor);
    }

    /** @return array<string, string> slugs do grupo na ordem do fluxo, => rótulo */
    public static function options(string $grupo): array
    {
        return collect(self::VALUES[$grupo] ?? [])
            ->mapWithKeys(fn (array $entrada, string $slug) => [$slug => $entrada[0]])
            ->all();
    }
}
