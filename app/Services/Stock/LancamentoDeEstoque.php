<?php

namespace App\Services\Stock;

use App\Models\Product;
use App\Models\ServiceOrder;
use App\Models\StockMovement;
use App\Models\Technician;
use App\Models\TechnicianStock;
use App\Models\User;
use App\Support\Auditor;
use App\Support\Formatters;
use App\Support\Notifier;
use App\Support\StatusCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A escrita do estoque: o degrau entre o formulário de movimentação e o banco.
 *
 * O desenho do projeto pede interface → serviço → backend → dados, e aqui o
 * lançamento deixa de ser um comando de controller. O que mora neste arquivo é o que
 * não se pode fazer por atalho: a conta dos dois saldos, a trava que faz a conta
 * valer, o carimbo de quem registrou e o sino de quem repõe.
 *
 * **Nenhum saldo fica negativo, e a leitura é travada.** Carga não pode passar do
 * que o técnico carrega, e consumo, devolução e ajuste não podem levar o central
 * abaixo de zero. A conferência acontece dentro de uma transação com o produto e a
 * linha de carga presos por `lockForUpdate`, na ordem produto → carga → saldo: duas
 * requisições que cada uma confere "tem bastante" na própria leitura é exatamente
 * como um estoque chega a −3 unidades.
 *
 * **A data é de cá.** `recorded_at` é o relógio do servidor, escrito aqui e em mais
 * nenhum lugar. Um pedido com data escolhida no navegador reescreveria a ordem dos
 * fatos e o saldo de um dia que já passou.
 *
 * **Movimentação não se edita nem se apaga.** É livro-caixa: o que estava errado se
 * responde com outra linha, e a auditoria mostra as duas. Por isso este serviço só
 * tem um caminho de entrada.
 *
 * A recusa dos dois saldos sai como `ValidationException` com o campo nomeado — não
 * como `Recusa` — porque é regra de formulário que devolve a tela com o número que
 * faltava embaixo do campo em que a pessoa errou, e não travessia proibida de
 * estado. O que a tela pergunta (qual produto, de quem é a carga, em qual ordem) é
 * resolvido acima daqui; aqui chega já resolvido.
 */
final class LancamentoDeEstoque
{
    /**
     * Registra o fato e devolve o que a apresentação precisa saber: a linha criada,
     * o produto, o saldo central depois do lançamento, a frase da tela e o aviso de
     * reposição — este último quando o saldo cruzou o ponto de reposição, que é
     * também quando o sino toca.
     *
     * @param  array{produto_id: int, tipo: string, quantidade: float, tecnico: ?Technician, ordem: ?ServiceOrder, usuario: User, custo: mixed, observacao: mixed}  $par
     * @return array{movimento: StockMovement, produto: Product, central: float, resumo: string, aviso: ?string}
     */
    public function registrar(array $par): array
    {
        [$movimento, $produto, $depois] = DB::transaction(fn (): array => $this->gravar($par));

        Auditor::gravar('movimentação de estoque', $movimento, [], $this->trilha($movimento, $produto, $depois));

        $aviso = $this->avisoDeReposicao($produto, $depois);

        if ($aviso !== null) {
            $this->avisarEstoqueBaixo($produto, $aviso);
        }

        return [
            'movimento' => $movimento,
            'produto' => $produto,
            'central' => $depois,
            'resumo' => $this->resumo($movimento, $produto, $depois),
            'aviso' => $aviso,
        ];
    }

    /**
     * A ordem dentro da transação é o que faz a trava valer: primeiro o produto,
     * depois a linha de carga, e só então os saldos são lidos do que o banco
     * devolve — antes de a linha existir.
     *
     * @param  array{produto_id: int, tipo: string, quantidade: float, tecnico: ?Technician, ordem: ?ServiceOrder, usuario: User, custo: mixed, observacao: mixed}  $par
     * @return array{0: StockMovement, 1: Product, 2: float}
     */
    private function gravar(array $par): array
    {
        $produto = Product::query()->whereKey($par['produto_id'])->lockForUpdate()->firstOrFail();
        $tipo = $par['tipo'];
        $quantidade = $par['quantidade'];
        $unidade = Formatters::unidade($produto->unit);

        $deltaCentral = round((StockMovement::CENTRAL_SIGN[$tipo] ?? 0) * $quantidade, 4);
        $deltaTecnico = $par['tecnico'] === null
            ? null
            : round((StockMovement::TECHNICIAN_SIGN[$tipo] ?? 0) * $quantidade, 4);

        $carga = $par['tecnico'] === null ? null : TechnicianStock::travar($par['tecnico'], $produto);

        $antes = $produto->saldoCentralAtual();

        if ($deltaCentral < 0 && $antes + $deltaCentral < 0) {
            throw ValidationException::withMessages([
                'quantidade' => sprintf(
                    'O estoque central de %s tem %s %s: %s de %s não cabe aí.',
                    $produto->name,
                    Formatters::decimal($antes),
                    $unidade,
                    StatusCatalog::label('movement', $tipo),
                    Formatters::decimal(abs($deltaCentral)),
                ),
            ]);
        }

        if ($deltaTecnico !== null && $deltaTecnico < 0 && (float) $carga->quantity + $deltaTecnico < 0) {
            throw ValidationException::withMessages([
                'tecnico_id' => sprintf(
                    '%s carrega %s %s de %s: %s de %s passa do que há na mala.',
                    $par['tecnico']->name,
                    Formatters::decimal($carga->quantity),
                    $unidade,
                    $produto->name,
                    StatusCatalog::label('movement', $tipo),
                    Formatters::decimal(abs($deltaTecnico)),
                ),
            ]);
        }

        $movimento = StockMovement::query()->create([
            'product_id' => $produto->id,
            'technician_id' => $par['tecnico']?->id,
            'service_order_id' => $par['ordem']?->id,
            'user_id' => $par['usuario']->id,
            'type' => $tipo,
            'quantity' => $quantidade,
            'unit_cost' => $par['custo'],
            'note' => filled($par['observacao']) ? $par['observacao'] : null,
            'recorded_at' => now(),
        ]);

        if ($deltaTecnico !== null) {
            $carga->aplicar($deltaTecnico);
        }

        return [$movimento, $produto, $produto->saldoCentralAtual()];
    }

    /**
     * A frase da tela: o que saiu ou entrou, e para onde foi o saldo. Diferente da
     * trilha porque quem acabou de digitar lê o efeito do próprio gesto, e quem lê a
     * auditoria meses depois lê o fato registrado.
     */
    private function resumo(StockMovement $movimento, Product $produto, float $depois): string
    {
        $unidade = Formatters::unidade($produto->unit);
        $central = $movimento->efeitoCentral();

        $texto = sprintf(
            '%s de %s %s de %s (%s). Estoque central: %s%s %s.',
            StatusCatalog::label('movement', $movimento->type),
            Formatters::decimal(abs((float) $movimento->quantity)),
            $unidade,
            $produto->name,
            $produto->sku,
            $central > 0 ? '+' : ($central < 0 ? '−' : ''),
            Formatters::decimal($central === 0 ? 0 : abs($central)),
            $unidade,
        );

        $clausula = $this->clausulaDaCarga($movimento, $unidade);

        return $clausula === '' ? $texto : rtrim($texto, '.').$clausula;
    }

    /** O carimbo da auditoria: o fato e o saldo que ele formou, com o número de depois. */
    private function trilha(StockMovement $movimento, Product $produto, float $depois): string
    {
        $unidade = Formatters::unidade($produto->unit);

        $texto = sprintf(
            '%s: %s %s de %s (%s). Estoque central agora: %s %s.',
            StatusCatalog::label('movement', $movimento->type),
            Formatters::decimal(abs((float) $movimento->quantity)),
            $unidade,
            $produto->name,
            $produto->sku,
            Formatters::decimal($depois),
            $unidade,
        );

        return $texto.$this->clausulaDaCarga($movimento, $unidade);
    }

    /**
     * O pedaço que a tela e a trilha dizem igual: quantas unidades entraram ou
     * saíram da mala de quem. Uma frase só, para não ter duas versões dela
     * divergindo no dia em que o texto mudar.
     */
    private function clausulaDaCarga(StockMovement $movimento, string $unidade): string
    {
        $carga = $movimento->efeitoTecnico();

        if ($carga === null) {
            return '';
        }

        return sprintf(
            ' %s %s %s na carga de %s.',
            $carga > 0 ? 'Entraram' : 'Saíram',
            Formatters::decimal(abs($carga)),
            $unidade,
            $movimento->technician?->name ?? 'técnico',
        );
    }

    /**
     * O saldo cruzou a linha e a operação precisa saber disso no momento em que
     * cruzou, não na semana em que alguém abrir o painel.
     */
    private function avisoDeReposicao(Product $produto, float $depois): ?string
    {
        if ($produto->reorder_point === null || $depois >= (float) $produto->reorder_point) {
            return null;
        }

        return sprintf(
            '%s está abaixo do ponto de reposição: %s contra o mínimo de %s %s.',
            $produto->name,
            Formatters::decimal($depois),
            Formatters::decimal($produto->reorder_point),
            Formatters::unidade($produto->unit),
        );
    }

    /**
     * A falta no central é o único flash de tela que também toca sino: quem vê o
     * aviso verde é quem digitou a baixa; quem repõe precisa ser chamado à parte. O
     * toque é um por conta sem leitura — a mesma peça voltando a faltar não martela
     * quem ainda não leu a falta anterior, e volta a soar para quem já leu.
     */
    private function avisarEstoqueBaixo(Product $produto, string $aviso): void
    {
        $link = route('movements.index', ['produto' => $produto->id]);

        foreach (Notifier::quemPode('stock.adjust') as $conta) {
            if (Notifier::jaAvisaram((int) $conta->id, 'estoque.baixo', $link)) {
                continue;
            }

            Notifier::para(
                $conta,
                'estoque.baixo',
                sprintf('%s abaixo do ponto de reposição', $produto->name),
                $aviso,
                $link,
                ['produto_id' => $produto->id],
            );
        }
    }
}
