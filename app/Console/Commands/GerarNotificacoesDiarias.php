<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Company;
use App\Models\FinancialRecord;
use App\Models\ServiceOrder;
use App\Models\Technician;
use App\Models\User;
use App\Support\Formatters;
use App\Support\Notifier;
use App\Support\Settings;
use App\Support\SettingsCatalog;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * A varredura diária do que não tem gatilho em tela nenhuma: a ordem cujo fim
 * previsto já passou, a conta que bate na data, o compromisso que é o trabalho de
 * amanhã. O relógio é o do servidor, e cada empresa é varrida dentro do próprio
 * contexto de tenant — aviso que atravessa a fronteira da empresa não é alerta, é
 * vazamento.
 *
 * A regra é a mesma para as três famílias: enquanto o sino daquela conta tiver a
 * leitura daquele fato pendente, a varredura não o toca outra vez. Insistência sem
 * leitura é spam, e spam ensina a pessoa a ignorar o sino.
 */
class GerarNotificacoesDiarias extends Command
{
    protected $signature = 'nf:notificacoes:diaria';

    protected $description = 'Gera os avisos recorrentes de atraso, vencimento e agenda do dia seguinte';

    public function handle(): int
    {
        $gerados = 0;

        foreach (Company::query()->where('status', 'active')->pluck('id') as $empresaId) {
            TenantContext::set((int) $empresaId);

            $gerados += $this->ordensAtrasadas()
                + $this->cobrancasVencendo()
                + $this->agendaDeAmanha();

            TenantContext::forget();
        }

        $this->info("{$gerados} aviso(s) gerados pela varredura diária.");

        return self::SUCCESS;
    }

    private function ordensAtrasadas(): int
    {
        if (! Settings::ligado(SettingsCatalog::AVISO_ORDENS_ATRASADAS)) {
            return 0;
        }

        $gerados = 0;

        foreach (ServiceOrder::query()->overdue()->orderBy('scheduled_ends_at')->get() as $ordem) {
            $título = sprintf('Ordem %s atrasada: %s', $ordem->number, $ordem->title);
            $corpo = sprintf(
                'Fim previsto em %s e a ordem segue %s.',
                Formatters::dateTime($ordem->scheduled_ends_at),
                mb_strtolower(StatusCatalog::label('order', $ordem->status)),
            );
            $link = route('orders.show', $ordem);

            foreach ($this->genteDaOrdem($ordem) as $conta) {
                if (Notifier::jaAvisaram((int) $conta->id, 'ordem.atrasada', $link)) {
                    continue;
                }

                if (Notifier::para($conta, 'ordem.atrasada', $título, $corpo, $link) !== null) {
                    $gerados++;
                }
            }
        }

        return $gerados;
    }

    /**
     * Quem ouve o atraso: o técnico responsável, o quadro de comissão da vez e o
     * escritório que aprova a escala. A conta de cliente é deixada de fora de
     * propósito — a carteira dela mostra o estado da ordem, e receber um "está
     * atrasada" sem ação nenhuma junto só gera ansiedade.
     *
     * @return Collection<int, User>
     */
    private function genteDaOrdem(ServiceOrder $ordem): Collection
    {
        $fichas = $ordem->assignments()
            ->whereNull('released_at')
            ->pluck('technician_id')
            ->push($ordem->technician_id)
            ->filter();

        $contas = User::query()
            ->where('company_id', TenantContext::id())
            ->where('status', 'active')
            ->whereIn('id', Technician::query()->whereIn('id', $fichas)->whereNotNull('user_id')->pluck('user_id'))
            ->get();

        return $contas->merge(Notifier::quemPode('orders.approve'))->unique('id');
    }

    private function cobrancasVencendo(): int
    {
        if (! Settings::ligado(SettingsCatalog::AVISO_VENCIMENTOS)) {
            return 0;
        }

        $gerados = 0;
        $hoje = now()->toDateString();
        $limite = now()->addDays(Settings::inteiro(SettingsCatalog::DIAS_ALERTA_VENCIMENTO))->toDateString();

        $registros = FinancialRecord::query()
            ->emAberto()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$hoje, $limite])
            ->orderBy('due_date')
            ->get();

        foreach ($registros as $registro) {
            $título = sprintf(
                '%s %s: %s',
                $registro->eReceita() ? 'Receita' : 'Despesa',
                $registro->due_date->isToday() ? 'vence hoje' : 'vence em '.Formatters::date($registro->due_date),
                $registro->description,
            );
            $corpo = sprintf(
                '%s · %s · saldo %s.',
                Formatters::money($registro->amount),
                FinancialRecord::rotuloCategoria($registro->category),
                Formatters::money($registro->saldo()),
            );
            $link = route('financial.show', $registro);

            foreach (Notifier::quemPode('financial.view') as $conta) {
                if (Notifier::jaAvisaram((int) $conta->id, 'financeiro.vencendo', $link)) {
                    continue;
                }

                if (Notifier::para($conta, 'financeiro.vencendo', $título, $corpo, $link) !== null) {
                    $gerados++;
                }
            }
        }

        return $gerados;
    }

    private function agendaDeAmanha(): int
    {
        if (! Settings::ligado(SettingsCatalog::AVISO_AGENDA)) {
            return 0;
        }

        $gerados = 0;
        $amanhã = now()->addDay();

        $compromissos = Appointment::query()
            ->scheduled()
            ->whereBetween('starts_at', [$amanhã->copy()->startOfDay(), $amanhã->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get();

        foreach ($compromissos as $compromisso) {
            $título = sprintf('Amanhã às %s: %s', Formatters::time($compromisso->starts_at), $compromisso->title);
            $corpo = filled($compromisso->location)
                ? $compromisso->location
                : ($compromisso->technician?->name ?? 'Sem técnico marcado na janela.');
            $link = route('agenda.show', $compromisso);

            $contas = User::query()
                ->where('company_id', TenantContext::id())
                ->where('status', 'active')
                ->where(fn ($q) => $q
                    ->whereIn('id', Technician::query()->whereKey($compromisso->technician_id)->whereNotNull('user_id')->pluck('user_id'))
                    ->orWhere('client_id', $compromisso->client_id))
                ->get();

            foreach ($contas as $conta) {
                if (Notifier::jaAvisaram((int) $conta->id, 'agenda.lembrete', $link)) {
                    continue;
                }

                if (Notifier::para($conta, 'agenda.lembrete', $título, $corpo, $link) !== null) {
                    $gerados++;
                }
            }
        }

        return $gerados;
    }
}
