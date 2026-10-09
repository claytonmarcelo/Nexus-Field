<?php

namespace App\Services\Orders;

use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderStatusHistory;
use App\Models\Technician;
use App\Models\User;
use App\Services\Recusa;
use App\Support\Notifier;
use App\Support\StatusCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A camada de serviço das ordens de serviço: o degrau entre o formulário e o banco.
 *
 * O desenho do projeto pede interface -> serviço -> backend -> dados, e este é o
 * lugar onde a escrita de uma ordem deixa de ser um comando de controller. Abrir uma
 * ordem são três escritas que não se separam — a linha, a travessia que a faz nascer
 * registrada e a comissão do técnico apontado; uma delas fora da transação deixaria
 * ordem sem nascimento na história, e a pergunta "quem abriu e quando" ficaria sem
 * resposta. Encerrar é o mesmo cuidado ao contrário: carimbo, passagem e o sino de
 * quem tem pele na ordem.
 *
 * A máquina de estados mora no modelo (`ServiceOrder::FLUXO`) e daqui também não
 * sai: o serviço acrescenta o que a máquina não sabe — se este autor pode mandar no
 * passo, e quem acorda depois dele. Estado não se digita: `atualizar()` escreve o
 * cadastro e ignora travessia, porque concluir e cancelar têm nota, carimbo e
 * permissão próprios.
 */
final class FluxoDeOrdem
{
    /**
     * O nascimento da ordem: número sequencial da empresa, passagem de abertura e
     * comissão do responsável, tudo numa transação — e o sino depois, quando já há
     * linha para linkar.
     *
     * @param  array<string, mixed>  $dados  o que a tela validou
     */
    public function abrir(User $autor, array $dados): ServiceOrder
    {
        $ordem = DB::transaction(function () use ($dados, $autor): ServiceOrder {
            $ordem = ServiceOrder::create([
                ...$this->normalizar($dados),
                'number' => ServiceOrder::proximoNumero(),
            ]);

            ServiceOrderStatusHistory::query()->create([
                'service_order_id' => $ordem->id,
                'user_id' => $autor->id,
                'from_status' => null,
                'to_status' => $ordem->status,
                'note' => 'Ordem aberta na tela de ordens.',
                'created_at' => now(),
            ]);

            if ($ordem->technician_id !== null) {
                $ordem->assignments()->create([
                    'technician_id' => $ordem->technician_id,
                    'assigned_by' => $autor->id,
                    'assigned_at' => now(),
                ]);
            }

            return $ordem;
        });

        $this->avisarNascimento($ordem, $autor);

        return $ordem;
    }

    /**
     * Cadastro da ordem. O estado não está entre os campos aceitos: a travessia é do
     * botão com carimbo, e um `status` vindo do formulário morre antes daqui, na
     * validação da tela.
     *
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(ServiceOrder $ordem, array $dados): ServiceOrder
    {
        $ordem->update($this->normalizar($dados));

        return $ordem;
    }

    /**
     * Aplicar um estado é a escrita completa: a máquina, o carimbo do momento, a
     * passagem registrada com quem fez e o sino de fim de percurso. Devolve o estado
     * de origem porque a tela conta a travessia — "Aberta → Em execução" — e depois
     * do write a ordem só sabe para onde foi.
     *
     * @throws Recusa no salto que o fluxo não tem, ou no passo que pede aprovação
     */
    public function mudarStatus(ServiceOrder $ordem, User $autor, string $destino, ?string $nota = null): string
    {
        if (! $ordem->podeMudarPara($destino)) {
            throw new Recusa(sprintf(
                'A ordem %s está “%s” e não pode ir para “%s”: o fluxo do módulo é o que vale.',
                $ordem->number,
                StatusCatalog::label('order', $ordem->status),
                StatusCatalog::label('order', $destino),
            ));
        }

        if (! array_key_exists($destino, $this->proximosEstados($ordem, $autor))) {
            throw new Recusa('Tirar um rascunho do papel e cancelar uma ordem pedem a permissão de aprovação.');
        }

        $origem = $ordem->status;

        $ordem->mudarStatus($destino, $autor, $nota);
        $this->avisarFimDePercurso($ordem, $destino, $autor);

        return $origem;
    }

    /**
     * Apagar é só do rascunho que nunca virou trabalho: ordem que saiu do papel tem
     * estado, carimbo e gente que mexeu nela. Devolve o número, porque a linha some
     * e a flash precisa dizer o que foi riscado.
     *
     * @throws Recusa
     */
    public function retirar(ServiceOrder $ordem): string
    {
        if ($ordem->status !== 'draft') {
            throw new Recusa(sprintf(
                'A ordem %s não é mais rascunho (%s), então não pode ser apagada. Cancele-a com o motivo registrado.',
                $ordem->number,
                StatusCatalog::label('order', $ordem->status),
            ));
        }

        $numero = $ordem->number;
        $ordem->delete();

        return $numero;
    }

    /**
     * Estados que a ficha oferece como botão: o fluxo do modelo decide o caminho e a
     * permissão de aprovação decide se o passo é deste autor. É a mesma régua que
     * `mudarStatus()` usa para deixar ou não passar — tela e serviço não têm duas
     * versões dela.
     *
     * @return array<string, string> slug => rótulo
     */
    public function proximosEstados(ServiceOrder $ordem, User $autor): array
    {
        $podeAprovar = $autor->hasPermission('orders.approve');

        return collect(ServiceOrder::FLUXO[$ordem->status] ?? [])
            ->reject(fn (string $destino) => in_array($destino, ServiceOrder::ESTADOS_APROVADOS, true) && ! $podeAprovar)
            ->mapWithKeys(fn (string $destino) => [$destino => StatusCatalog::label('order', $destino)])
            ->all();
    }

    /** @return array<string, string> */
    public function estadosIniciais(User $autor): array
    {
        $aceitos = $autor->hasPermission('orders.approve')
            ? ServiceOrder::ESTADOS_INICIAIS
            : ['draft'];

        return array_intersect_key(StatusCatalog::options('order'), array_flip($aceitos));
    }

    /**
     * O que a tela não pergunta, o serviço sabe: fim previsto pela duração estimada
     * do serviço e UF sempre em duas maiúsculas.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    public function normalizar(array $dados): array
    {
        $inicio = $dados['scheduled_starts_at'] ?? null;
        $fim = $dados['scheduled_ends_at'] ?? null;

        if ($inicio !== null && $fim === null && filled($dados['service_id'] ?? null)) {
            $duracao = Service::query()->whereKey($dados['service_id'])->value('estimated_minutes');

            if ($duracao !== null) {
                $fim = Carbon::parse($inicio)->addMinutes((int) $duracao)->toDateTimeString();
            }
        }

        $dados['scheduled_ends_at'] = $fim;

        if (filled($dados['state'] ?? null)) {
            $dados['state'] = mb_strtoupper($dados['state']);
        }

        return $dados;
    }

    /**
     * Ordem recém-criada toca no sino de quem tem de mexer nela: se já nasceu com
     * técnico, a conta da ficha dele; se nasceu sem responsável, a decisão sobe para
     * quem tem a permissão de aprovação da escala. Em ambos os casos quem abriu a
     * ordem não ouve o próprio sino — o autor acabou de ver o ato.
     */
    private function avisarNascimento(ServiceOrder $ordem, User $autor): void
    {
        $título = sprintf('Ordem %s criada para %s.', $ordem->number, $ordem->client->name);
        $link = route('orders.show', $ordem);

        if ($ordem->technician?->user !== null) {
            Notifier::para($ordem->technician->user, 'ordem.criada', $título, $ordem->title, $link, [
                'order_id' => $ordem->id,
            ]);

            return;
        }

        Notifier::paraQuemPode('orders.approve', 'ordem.criada', $título, $ordem->title, $link, [
            'order_id' => $ordem->id,
        ], $autor);
    }

    /**
     * Só o fim de percurso toca sino: concluída ou cancelada. O carimbo do meio do
     * fluxo ("em execução", "pausada") é gesto interno do escritório e não acorda
     * ninguém. A audiência é de quem tem pele na ordem — as contas do técnico
     * responsável, do quadro de comissão ativo e do cliente dono da carteira — e o
     * autor do carimbo fica de fora. Quando o fim é o cancelamento, o motivo
     * registrado viaja como corpo do aviso: quem esperava o serviço tem direito de
     * saber por que não veio.
     */
    private function avisarFimDePercurso(ServiceOrder $ordem, string $destino, User $autor): void
    {
        if (! in_array($destino, ['completed', 'canceled'], true)) {
            return;
        }

        $tipo = $destino === 'completed' ? 'ordem.concluida' : 'ordem.cancelada';
        $título = sprintf('Ordem %s %s.', $ordem->number, mb_strtolower(StatusCatalog::label('order', $destino)));
        $corpo = $destino === 'canceled' ? $ordem->cancellation_reason : null;
        $link = route('orders.show', $ordem);

        $fichas = $ordem->assignments()->whereNull('released_at')->pluck('technician_id');

        if ($ordem->technician_id !== null) {
            $fichas = $fichas->push($ordem->technician_id);
        }

        $contas = User::query()
            ->where('company_id', $ordem->company_id)
            ->where('status', 'active')
            ->where(fn ($q) => $q
                ->whereIn('id', Technician::query()->whereIn('id', $fichas)->whereNotNull('user_id')->pluck('user_id'))
                ->orWhere('client_id', $ordem->client_id))
            ->where('id', '!=', $autor->id)
            ->get();

        foreach ($contas as $conta) {
            Notifier::para($conta, $tipo, $título, $corpo, $link, ['order_id' => $ordem->id]);
        }
    }
}
