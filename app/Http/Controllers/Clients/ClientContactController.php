<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Concerns\TrataRegistrosAninhados;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Contatos de um cliente. Vivem dentro da ficha: o cadastro do cliente é o dono
 * da relação, e nada aqui pode ser alcançado por fora dela.
 *
 * Os campos chegam prefixados (contato_*) porque o formulário de endereço está na
 * mesma tela e compartilha a caixa de erros: sem prefixo, um erro em `is_primary`
 * não saberia a qual dos dois formulários pertence.
 */
class ClientContactController extends Controller
{
    use TrataRegistrosAninhados;

    public function store(Request $request, Client $cliente): RedirectResponse
    {
        $contato = $cliente->contacts()->create($this->dados($request));

        $this->garantirUnicoPrincipal($cliente, $contato);

        return back()->with('status', "Contato “{$contato->name}” adicionado a {$cliente->name}.");
    }

    public function update(Request $request, Client $cliente, ClientContact $contato): RedirectResponse
    {
        $this->garantirQueEPecaDoCadastro($cliente, $contato, 'client');

        $contato->update($this->dados($request));

        $this->garantirUnicoPrincipal($cliente, $contato);

        return redirect()
            ->route('clients.show', $cliente)
            ->with('status', "Contato “{$contato->name}” atualizado.");
    }

    public function destroy(Client $cliente, ClientContact $contato): RedirectResponse
    {
        $this->garantirQueEPecaDoCadastro($cliente, $contato, 'client');

        $nome = $contato->name;
        $contato->delete();

        return back()->with('status', "Contato “{$nome}” removido.");
    }

    /** @return array<string, mixed> */
    private function dados(Request $request): array
    {
        $validado = $request->validate([
            'contato_nome' => ['required', 'string', 'max:120'],
            'contato_email' => ['nullable', 'email', 'max:180'],
            'contato_telefone' => ['nullable', 'string', 'max:30'],
            'contato_cargo' => ['nullable', 'string', 'max:80'],
            'contato_principal' => ['boolean'],
        ]);

        return [
            'name' => $validado['contato_nome'],
            'email' => $validado['contato_email'] ?? null,
            'phone' => $validado['contato_telefone'] ?? null,
            'role' => $validado['contato_cargo'] ?? null,
            'is_primary' => (bool) ($validado['contato_principal'] ?? false),
        ];
    }

    /**
     * Contato principal é um, não uma lista. Ao promover um, os demais perdem a
     * marca no mesmo pedido — a tela nunca deixa duas linhas marcadas.
     */
    private function garantirUnicoPrincipal(Client $cliente, ClientContact $promovido): void
    {
        if (! $promovido->is_primary) {
            return;
        }

        $cliente->contacts()->whereKeyNot($promovido->id)->where('is_primary', true)->update(['is_primary' => false]);
    }
}
