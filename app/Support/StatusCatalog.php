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
            // Os rótulos que a pessoa lê saem de `FinancialRecord::rotuloEstado()`,
            // que sabe se a conta é receita ou despesa ("recebido" e "pago" são a
            // mesma quitação em direções contrárias). Aqui fica o tom, que é só dele.
            'pending' => ['Em aberto', 'open'],
            'partially_paid' => ['Pagamento parcial', 'waiting'],
            'paid' => ['Quitado', 'done'],
            'canceled' => ['Cancelado', 'canceled'],
            // Derivado da relação due_date com hoje; não é coluna da tabela.
            'overdue' => ['Vencido', 'canceled'],
        ],
        'client' => [
            'active' => ['Ativo', 'done'],
            'inactive' => ['Inativo', 'draft'],
        ],
        // Serviços e produtos vivem o mesmo vocabulário de catálogo.
        'catalogo' => [
            'active' => ['Ativo', 'done'],
            'inactive' => ['Inativo', 'draft'],
        ],
        'team' => [
            'active' => ['Ativa', 'done'],
            'inactive' => ['Inativa', 'draft'],
        ],
        'user' => [
            'active' => ['Ativo', 'done'],
            'inactive' => ['Inativo', 'canceled'],
        ],
        'company' => [
            'active' => ['Ativa', 'done'],
            'inactive' => ['Inativa', 'draft'],
            'suspended' => ['Suspensa', 'canceled'],
        ],
        'subscription' => [
            'trial' => ['Período de teste', 'open'],
            'active' => ['Assinatura ativa', 'done'],
            'past_due' => ['Pagamento atrasado', 'progress'],
            'expired' => ['Expirada', 'canceled'],
            'canceled' => ['Cancelada', 'draft'],
        ],
        'appointment' => [
            'scheduled' => ['Agendado', 'open'],
            'completed' => ['Concluído', 'done'],
            'canceled' => ['Cancelado', 'canceled'],
        ],
        'checkin' => [
            'open' => ['Em campo', 'progress'],
            'closed' => ['Encerrado', 'done'],
        ],
        'movement' => [
            'purchase' => ['Compra', 'done'],
            'load' => ['Carga para o técnico', 'open'],
            'consume' => ['Consumo em ordem de serviço', 'progress'],
            'return' => ['Devolução', 'waiting'],
            'adjustment' => ['Ajuste de inventário', 'draft'],
        ],
        'financial_type' => [
            'revenue' => ['Receita', 'done'],
            'expense' => ['Despesa', 'canceled'],
        ],
        'payment_method' => [
            'pix' => ['PIX', 'open'],
            'credit_card' => ['Cartão de crédito', 'waiting'],
            'debit_card' => ['Cartão de débito', 'waiting'],
            'cash' => ['Dinheiro', 'progress'],
            'transfer' => ['Transferência', 'done'],
        ],
        'address' => [
            'service' => ['Assistência técnica', 'open'],
            'commercial' => ['Ponto comercial', 'waiting'],
            'storage' => ['Depósito', 'draft'],
        ],
        'appointment_type' => [
            'order' => ['Ordem de serviço', 'open'],
            'visit' => ['Visita técnica', 'waiting'],
            'ticket' => ['Chamado', 'progress'],
            'custom' => ['Compromisso interno', 'draft'],
        ],

        // O vocabulário do sino: o tipo gravado pelo Notifier ganha rótulo e tom
        // aqui, e é daqui que a central filtra — a tela não conhece texto solto.
        'notification' => [
            'ordem.criada' => ['Ordem criada', 'open'],
            'ordem.atribuida' => ['Ordem atribuída', 'progress'],
            'ordem.concluida' => ['Ordem concluída', 'done'],
            'ordem.cancelada' => ['Ordem cancelada', 'canceled'],
            'ordem.atrasada' => ['Ordem atrasada', 'canceled'],
            'chamdo.aberto' => ['Chamado aberto', 'open'],
            'chamdo.resolvido' => ['Chamado resolvido', 'done'],
            'estoque.baixo' => ['Estoque baixo', 'waiting'],
            'financeiro.vencendo' => ['Cobrança vencendo', 'waiting'],
            'agenda.lembrete' => ['Lembrete de agenda', 'progress'],
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
