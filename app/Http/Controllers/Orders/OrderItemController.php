<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Concerns\TrataRegistrosAninhados;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Orders\Concerns\EnxergaAOrdem;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Support\Auditor;
use App\Support\Formatters;
use App\Support\StatusCatalog;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Itens cobrados de uma ordem. A linha nasce do catálogo, mas não é uma referência
 * viva: preço e descrição ficam congelados ali, porque o que foi cobrado naquele
 * dia continua valendo depois que o serviço mudar de preço.
 *
 * Os campos vêm prefixados (`item_*`) porque o formulário do quadro de comissão
 * está na mesma ficha e dividiria a caixa de erros.
 */
class OrderItemController extends Controller
{
    use EnxergaAOrdem, TrataRegistrosAninhados;

    public function store(Request $request, ServiceOrder $ordem): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($ordem, $usuario);

        if ($ordem->estaEncerrada()) {
            return $this->recusar($ordem, 'itens');
        }

        $validado = $request->validate($this->regras(), $this->mensagens());
        $peca = $this->catalogoDaLinha($validado);

        $item = $ordem->items()->create([
            'service_id' => $peca instanceof Service ? $peca->id : null,
            'product_id' => $peca instanceof Product ? $peca->id : null,
            'description' => filled($validado['item_descricao'] ?? null) ? $validado['item_descricao'] : $peca->name,
            'quantity' => $validado['item_quantidade'],
            'unit_price' => $validado['item_valor'] ?? (float) $peca->price,
            'discount' => $validado['item_desconto'] ?? 0,
        ]);

        Auditor::gravar(
            'item adicionado',
            $ordem,
            [],
            sprintf('%s: linha “%s” cobrada a %s.', $ordem->number, $item->description, Formatters::money($item->total())),
        );

        return back()->with('status', "Linha “{$item->description}” adicionada à ordem {$ordem->number}.");
    }

    /**
     * Editar linha mexe em quantidade, desconto e descrição — nunca em quem é o
     * item. Trocar o serviço pelo produto é outro cadastro de linha, e é assim que
     * a tela diz.
     */
    public function update(Request $request, ServiceOrder $ordem, ServiceOrderItem $item): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($ordem, $usuario);
        $this->garantirQueEPecaDoCadastro($ordem, $item, 'service_order');

        if ($ordem->estaEncerrada()) {
            return $this->recusar($ordem, 'itens');
        }

        $validado = $request->validate([
            'item_quantidade' => ['required', 'numeric', 'min:0.0001', 'max:99999'],
            'item_valor' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'item_desconto' => ['nullable', 'numeric', 'min:0', $this->descontoDaLinha(...)],
            'item_descricao' => ['nullable', 'string', 'max:180'],
        ], $this->mensagens());

        $antes = $item->total();

        $item->update([
            'quantity' => $validado['item_quantidade'],
            'unit_price' => $validado['item_valor'] ?? $item->unit_price,
            'discount' => $validado['item_desconto'] ?? 0,
            'description' => filled($validado['item_descricao'] ?? null) ? $validado['item_descricao'] : $item->description,
        ]);

        Auditor::gravar(
            'item alterado',
            $ordem,
            [],
            sprintf('%s: linha “%s” de %s para %s.', $ordem->number, $item->description, Formatters::money($antes), Formatters::money($item->total())),
        );

        return redirect()
            ->route('orders.show', $ordem)
            ->with('status', "Linha “{$item->description}” atualizada.");
    }

    public function destroy(Request $request, ServiceOrder $ordem, ServiceOrderItem $item): RedirectResponse
    {
        $usuario = $request->user();
        $this->garantirVisivel($ordem, $usuario);
        $this->garantirQueEPecaDoCadastro($ordem, $item, 'service_order');

        if ($ordem->estaEncerrada()) {
            return $this->recusar($ordem, 'itens');
        }

        $descricao = $item->description;
        $item->delete();

        Auditor::gravar('item removido', $ordem, [], sprintf('%s: linha “%s” removida.', $ordem->number, $descricao));

        return back()->with('status', "Linha “{$descricao}” removida da ordem {$ordem->number}.");
    }

    /**
     * Ordem encerrada é conta fechada: mexer em linha depois de concluir ou
     * cancelar reescreveria o que o cliente já pagou. A tela de ordens não oferece
     * isso, e o servidor também não.
     */
    private function recusar(ServiceOrder $ordem, string $oQue): RedirectResponse
    {
        return redirect()
            ->route('orders.show', $ordem)
            ->with('erro', sprintf(
                'A ordem %s está %s e por isso não aceita mudança de %s. Abra outra ordem para corrigir o que foi cobrado.',
                $ordem->number,
                mb_strtolower(StatusCatalog::label('order', $ordem->status)),
                $oQue,
            ));
    }

    /**
     * A linha é serviço OU produto, nunca os dois e nunca nenhum — é o mesmo
     * CHECK que o banco impõe em `service_order_items`, respondido antes de virar
     * erro de SQL na tela.
     *
     * @param  array<string, mixed>  $validado
     */
    private function catalogoDaLinha(array $validado): Service|Product
    {
        return filled($validado['item_servico'] ?? null)
            ? Service::query()->findOrFail($validado['item_servico'])
            : Product::query()->findOrFail($validado['item_produto']);
    }

    /** @return array<string, mixed> */
    private function regras(): array
    {
        $empresa = TenantContext::id();

        return [
            'item_servico' => ['required_without:item_produto', Rule::exists('services', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at')),
                $this->linhaDeUmSoTipo('item_produto'),
            ],
            'item_produto' => ['required_without:item_servico', Rule::exists('products', 'id')
                ->where(fn ($query) => $query->where('company_id', $empresa)->whereNull('deleted_at')),
                $this->linhaDeUmSoTipo('item_servico'),
            ],
            'item_quantidade' => ['required', 'numeric', 'min:0.0001', 'max:99999'],
            'item_valor' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'item_desconto' => ['nullable', 'numeric', 'min:0', $this->descontoDaLinha(...)],
            'item_descricao' => ['nullable', 'string', 'max:180'],
        ];
    }

    /**
     * A linha é serviço OU produto consumido, nunca os dois — o mesmo CHECK que o
     * banco impõe em `service_order_items`, respondido antes de virar erro de SQL
     * na tela.
     */
    private function linhaDeUmSoTipo(string $outro): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falha) use ($outro): void {
            if (filled($valor) && filled(request()->input($outro))) {
                $falha('A linha é um serviço ou um produto consumido, nunca os dois.');
            }
        };
    }

    /**
     * O desconto de uma linha não pode comer a linha inteira: `max:` dinâmico
     * depende da quantidade e do valor que chegaram no mesmo pedido.
     */
    private function descontoDaLinha(string $atributo, mixed $valor, Closure $falha): void
    {
        $pedido = request();
        $quantidade = (float) ($pedido->input('item_quantidade') ?? 1);
        $unitario = (float) ($pedido->input('item_valor') ?? 0);

        if ($unitario <= 0 || (float) $valor <= round($quantidade * $unitario, 2)) {
            return;
        }

        $falha(sprintf(
            'O desconto da linha (%s) passa do que ela cobra (%s).',
            Formatters::money((float) $valor),
            Formatters::money($quantidade * $unitario),
        ));
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'item_servico.required_without' => 'Escolha o serviço do catálogo ou o produto consumido.',
            'item_produto.required_without' => 'Escolha o serviço do catálogo ou o produto consumido.',
            'item_servico.exists' => 'O serviço precisa ser um item do seu catálogo.',
            'item_produto.exists' => 'O produto precisa ser um item do seu catálogo.',
            'item_quantidade.min' => 'A quantidade tem de ser maior que zero.',
        ];
    }
}
