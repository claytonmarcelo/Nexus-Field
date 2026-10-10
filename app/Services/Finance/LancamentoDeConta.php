<?php

namespace App\Services\Finance;

use App\Models\Client;
use App\Models\FinancialRecord;
use App\Models\ServiceOrder;
use App\Services\Recusa;
use App\Support\Auditor;
use App\Support\Formatters;
use Illuminate\Validation\ValidationException;

/**
 * A escrita da conta: o degrau entre o formulário do financeiro e o banco.
 *
 * O desenho do projeto pede interface → serviço → backend → dados, e aqui a linha
 * do lançamento deixa de ser comando de controller. Este é o único lugar onde um
 * `FinancialRecord` nasce, é alterado, cancelado, reaberto, apagado ou restaurado,
 * e por isso ele não recebe Request: quem traduz a tela é o controller, aqui chega
 * o que já foi validado.
 *
 * **Estado e data do fato não se digitam.** Uma conta nasce em aberto, sem
 * `occurred_at`, porque não aconteceu nada ainda; quem move esse estado é o
 * dinheiro registrado em `RegistroDePagamento`, nunca este método nem o formulário
 * dele. É por isso que `prepara()` escreve `status` só no nascimento e jamais
 * toca em `occurred_at` numa alteração.
 *
 * **Duas faltas, duas portas.** O que é regra de campo — ordem e cliente de
 * carteiras diferentes, valor mexido depois de haver dinheiro, cancelamento que
 * apagaria pagamento — devolve `ValidationException` com o campo nomeado, porque a
 * resposta tem de cair embaixo do campo em que a pessoa errou. O que é travessia
 * proibida — cobrar ordem inacabada, emitir segunda cobrança da mesma OS, reabrir
 * conta que não foi cancelada, apagar conta com dinheiro — devolve `Recusa`, e a
 * tela devolve 302 com o motivo escrito, nunca a exceção na cara do visitante.
 *
 * **O valor de uma ordem não entra pelo request.** `emitirCobranca()` recomputa a
 * conta no banco (itens, descontos e o da ordem) e recusa ordem que não terminou,
 * cobrança já existente e total zerado, porque cobrar R$ 900 de uma OS que somou
 * R$ 1.480 é a fatura errada com a assinatura do escritório.
 */
final class LancamentoDeConta
{
    /** Ordens que podem virar cobrança: a conta fecha quando o serviço terminou. */
    public const ORDEM_COBRAVEL = 'completed';

    /**
     * Emite a conta a partir do que o formulário validou e grava o verbo da
     * operação na trilha — o `created` do model já tinha deixado a linha de
     * cadastro; aqui fica o que o escritório faz quando abre uma conta.
     *
     * @param  array<string, mixed>  $validado
     */
    public function criar(array $validado): FinancialRecord
    {
        $lancamento = FinancialRecord::query()->create($this->prepara($validado));

        Auditor::gravar('lançamento financeiro', $lancamento, [], sprintf(
            '%s de %s — %s, vencendo em %s.',
            $lancamento->eReceita() ? 'Receita' : 'Despesa',
            Formatters::money($lancamento->amount),
            $lancamento->description,
            Formatters::date($lancamento->due_date),
        ));

        return $lancamento;
    }

    /**
     * Altera a conta. Valor e tipo só se mexem enquanto não houve pagamento: mudar
     * o valor de uma conta meio paga é mover a régua debaixo do dinheiro que já
     * entrou, e o estorno é o caminho honesto para isso.
     *
     * @param  array<string, mixed>  $validado
     */
    public function alterar(FinancialRecord $registro, array $validado): FinancialRecord
    {
        if ($registro->temPagamento()) {
            $this->garantirContaEstavel($registro, $validado);
        }

        $registro->update($this->prepara($validado, $registro));

        return $registro;
    }

    /**
     * Cancelar é dizer que a conta nunca passou a existir para o caixa. Só acontece
     * sem pagamento registrado — desfazer dinheiro que entrou é estorno, tem outro
     * verbo e outra permissão — e exige o motivo, que é o que a auditoria e quem
     * abre a ficha depois vão ler.
     */
    public function cancelar(FinancialRecord $registro, string $motivo): void
    {
        if ($registro->temPagamento()) {
            throw ValidationException::withMessages([
                'motivo' => sprintf(
                    'Esta conta tem %s de %s registrado. Estorne o pagamento antes de cancelar: '
                    .'cancelamento apaga a expectativa de caixa, não o dinheiro que mudou de mão.',
                    $registro->payments()->count() === 1 ? 'um pagamento' : $registro->payments()->count().' pagamentos',
                    Formatters::money($registro->valorPago()),
                ),
            ]);
        }

        if ($registro->status === FinancialRecord::CANCELED) {
            throw new Recusa('Esta conta já está cancelada.');
        }

        $registro->update([
            'status' => FinancialRecord::CANCELED,
            'occurred_at' => null,
            'notes' => $this->anotar(strval($registro->notes), 'Cancelamento', $motivo),
        ]);

        Auditor::gravar('cancelamento de conta', $registro, [], sprintf(
            '%s cancelada: %s',
            $registro->description,
            $motivo,
        ));
    }

    /**
     * Reabrir devolve a conta à derivação: sem pagamento, ela nasce em aberto de
     * novo. Devolve o estado que ficou valendo porque é ele que a tela anuncia.
     */
    public function reabrir(FinancialRecord $registro): string
    {
        if ($registro->status !== FinancialRecord::CANCELED) {
            throw new Recusa('Só se reabre uma conta que foi cancelada.');
        }

        // A reabertura retira a decisão, e só isso: `recalcularEstado()` se recusa a
        // mexer numa linha ainda marcada como cancelada, então é aqui que o
        // cancelamento deixa de existir. O estado que vale volta da soma dos
        // pagamentos, e uma conta com dinheiro registrado não renasce em aberto.
        $registro->update([
            'status' => FinancialRecord::PENDING,
            'notes' => $this->anotar(strval($registro->notes), 'Reabertura', 'Conta devolvida à carteira.'),
        ]);

        $estado = $registro->recalcularEstado();

        Auditor::gravar('reabertura de conta', $registro, [], sprintf('%s reaberta.', $registro->description));

        return $estado;
    }

    /**
     * Excluir lançamento é caso de cadastro errado, não de operação: conta com
     * pagamento tem dinheiro registrado, e apagar a ficha deixaria o pagamento sem
     * dona na hora em que o relatório somar o mês.
     */
    public function apagar(FinancialRecord $registro): void
    {
        if ($registro->temPagamento()) {
            throw new Recusa(sprintf(
                'Esta conta tem %s de pagamento registrado. Para tirá-la da carteira sem perder o dinheiro, cancele-a '
                .'depois de estornar; exclusão é para cadastro que nunca existiu.',
                Formatters::money($registro->valorPago()),
            ));
        }

        $registro->delete();
    }

    /** Tira a conta da exclusão suave. O `restored` do model deixa a trilha sozinha. */
    public function restaurar(int $id): FinancialRecord
    {
        $linha = FinancialRecord::withTrashed()->findOrFail($id);
        $linha->restore();

        return $linha;
    }

    /**
     * Cobra a ordem no valor que o banco calcula. O request traz a data e a
     * categoria; valor, cliente e total são lidos da ordem, porque cobrar R$ 900 de
     * uma OS que somou R$ 1.480 é a fatura errada com a assinatura do escritório.
     *
     * @param  array<string, mixed>  $validado
     */
    public function emitirCobranca(ServiceOrder $ordem, array $validado): FinancialRecord
    {
        if ($ordem->status !== self::ORDEM_COBRAVEL) {
            throw new Recusa($ordem->estaEncerrada()
                ? sprintf('A ordem %s foi cancelada: o que não aconteceu não se cobra.', $ordem->number)
                : sprintf('A ordem %s ainda não terminou. A cobrança fecha a conta do serviço concluído.', $ordem->number));
        }

        $existente = FinancialRecord::query()
            ->where('service_order_id', $ordem->id)
            ->where('type', FinancialRecord::REVENUE)
            ->where('status', '!=', FinancialRecord::CANCELED)
            ->orderBy('due_date')
            ->first();

        if ($existente !== null) {
            throw new Recusa(sprintf(
                'A ordem %s já tem a cobrança "%s" em carteira. Se o valor mudou, ajuste a conta existente; '
                .'cobrança duplicada não é segunda via, é conflito.',
                $ordem->number,
                $existente->description,
            ));
        }

        $ordem->load('items');
        $total = $ordem->totais()['total'];

        if ($total <= 0) {
            throw new Recusa(sprintf(
                'A ordem %s não tem o que cobrar: a conta fecha em %s. '
                .'Registre as linhas do serviço antes de emitir a cobrança.',
                $ordem->number,
                Formatters::money($total),
            ));
        }

        $lancamento = FinancialRecord::query()->create([
            'client_id' => $ordem->client_id,
            'service_order_id' => $ordem->id,
            'type' => FinancialRecord::REVENUE,
            'category' => strval($validado['categoria']),
            'description' => 'Cobrança da '.$ordem->number.' — '.$ordem->title,
            'amount' => $total,
            'due_date' => $validado['vencimento'],
            'status' => FinancialRecord::PENDING,
            'notes' => filled($validado['observacao'] ?? null) ? $validado['observacao'] : null,
        ]);

        Auditor::gravar('cobrança de ordem', $lancamento, [], sprintf(
            'Cobrança emitida da %s no valor calculado do banco (%s).',
            $ordem->number,
            Formatters::money($total),
        ));

        return $lancamento;
    }

    /**
     * O que o formulário manda, convertido no que a tabela grava. `status` e
     * `occurred_at` não estão aqui porque não se digita o que se deriva, e
     * `company_id` vem do contexto do tenant, não do request.
     *
     * @param  array<string, mixed>  $validado
     * @return array<string, mixed>
     */
    private function prepara(array $validado, ?FinancialRecord $registro = null): array
    {
        $tipo = strval($validado['tipo']);
        $receita = $tipo === FinancialRecord::REVENUE;

        $dados = [
            'type' => $tipo,
            'category' => strval($validado['categoria']),
            'description' => strval($validado['descricao']),
            'amount' => (float) $validado['valor'],
            'due_date' => $validado['vencimento'],
            // Receita sem cliente não vira `0`: zero não é carteira nenhuma, é um
            // estrangeiro que o MySQL devolve como erro de chave. Quem não tem cliente
            // fica em NULL, o mesmo estado que a coluna assume quando o cliente se vai.
            'client_id' => $receita && filled($validado['cliente_id'] ?? null) ? (int) $validado['cliente_id'] : null,
            'service_order_id' => filled($validado['ordem_id'] ?? null) ? (int) $validado['ordem_id'] : null,
            'notes' => filled($validado['observacao'] ?? null) ? strval($validado['observacao']) : null,
        ];

        if ($registro === null) {
            $dados['status'] = FinancialRecord::PENDING;
            $dados['occurred_at'] = null;
        }

        $this->garantirMesmaCarteira($dados);

        return $dados;
    }

    /**
     * Ordem e cliente apontam para a mesma carteira. A validação de campo confere
     * cada um isoladamente e não enxerga o par: é aqui que a cobrança da OS do
     * Restaurante sai contra a Padaria, que é o erro que só aparece quando o
     * cliente liga reclamando do boleto.
     *
     * @param  array<string, mixed>  $dados
     */
    private function garantirMesmaCarteira(array $dados): void
    {
        if ($dados['service_order_id'] === null || $dados['client_id'] === null) {
            return;
        }

        $ordem = ServiceOrder::query()->whereKey($dados['service_order_id'])->first();

        if ($ordem !== null && (int) $ordem->client_id === (int) $dados['client_id']) {
            return;
        }

        throw ValidationException::withMessages([
            'ordem_id' => sprintf(
                'A ordem %s pertence a %s, e esta conta está aberta contra %s: escolha o cliente da ordem '
                .'ou a ordem do cliente.',
                $ordem?->number ?? 'inexistente',
                $ordem?->client?->name ?? 'carteira nenhuma',
                Client::query()->whereKey($dados['client_id'])->value('name') ?? 'carteira nenhuma',
            ),
        ]);
    }

    /**
     * O que já tem dinheiro dentro não muda de tamanho nem de lado. A validação de
     * campo aceita valor e tipo porque são campos legítimos do formulário — é aqui,
     * com a conta na mão, que se compara o que chegou com o que está gravado, e a
     * mensagem cai no campo que a pessoa tocou.
     *
     * @param  array<string, mixed>  $validado
     */
    private function garantirContaEstavel(FinancialRecord $registro, array $validado): void
    {
        $mudouValor = abs((float) $validado['valor'] - (float) $registro->amount) >= 0.005;
        $mudouTipo = strval($validado['tipo']) !== $registro->type;

        if (! $mudouValor && ! $mudouTipo) {
            return;
        }

        throw ValidationException::withMessages([
            $mudouValor ? 'valor' : 'tipo' => sprintf(
                'Esta conta já tem %s registrado. Mexer no %s depois disso é mover a régua debaixo do dinheiro: '
                .'estorne o pagamento e ajuste, ou cancele e reabra.',
                Formatters::money($registro->valorPago()),
                $mudouValor ? 'valor' : 'o tipo',
            ),
        ]);
    }

    /**
     * O carimbo que sobrevive à decisão: motivo e reabertura entram como linha no
     * campo de nota, com a data da casa, porque cancelar e reabrir apagam o estado
     * anterior e a explicação é a única coisa que não pode sumir junto.
     */
    private function anotar(?string $atual, string $titulo, string $texto): string
    {
        $linha = '['.Formatters::date(now()).'] '.$titulo.': '.$texto;

        return trim((string) $atual) === '' ? $linha : $atual."\n".$linha;
    }
}
