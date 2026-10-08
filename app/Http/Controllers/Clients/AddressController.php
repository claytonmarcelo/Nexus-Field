<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Concerns\TrataRegistrosAninhados;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Client;
use App\Support\StatusCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endereços de um cliente. A tabela é polimórfica, então o mesmo componente atende
 * outros donos no futuro — e é deste endereço que o check-in de campo (FASE 15)
 * vai medir a distância, por isso latitude e longitude têm lugar aqui.
 *
 * Os campos vêm prefixados (endereco_*) porque a ficha tem dois formulários
 * dividindo a mesma caixa de erros.
 */
class AddressController extends Controller
{
    use TrataRegistrosAninhados;

    public function store(Request $request, Client $cliente): RedirectResponse
    {
        // O dono da relação vem da rota, nunca do payload: um `addressable_id`
        // enviado pelo formulário carimbaria endereço de um cliente no vizinho.
        $endereco = $cliente->addresses()->create($this->dados($request));

        $this->garantirUnicoPrincipal($cliente, $endereco);

        return back()->with('status', "Endereço de {$endereco->city}/{$endereco->state} adicionado a {$cliente->name}.");
    }

    public function update(Request $request, Client $cliente, Address $endereco): RedirectResponse
    {
        $this->garantirQueEPerecoDoCadastro($cliente, $endereco, 'addressable');

        $endereco->update($this->dados($request));
        $this->garantirUnicoPrincipal($cliente, $endereco);

        return redirect()
            ->route('clients.show', $cliente)
            ->with('status', "Endereço de {$endereco->city}/{$endereco->state} atualizado.");
    }

    public function destroy(Client $cliente, Address $endereco): RedirectResponse
    {
        $this->garantirQueEPerecoDoCadastro($cliente, $endereco, 'addressable');

        $cidade = $endereco->city.'/'.$endereco->state;
        $endereco->delete();

        return back()->with('status', "Endereço em {$cidade} removido.");
    }

    private function garantirUnicoPrincipal(Client $cliente, Address $promovido): void
    {
        if (! $promovido->is_primary) {
            return;
        }

        $cliente->addresses()->whereKeyNot($promovido->id)->where('is_primary', true)->update(['is_primary' => false]);
    }

    /** @return array<string, mixed> */
    private function dados(Request $request): array
    {
        $validado = $request->validate([
            'endereco_tipo' => ['required', Rule::in(array_keys(StatusCatalog::options('address')))],
            'endereco_cep' => ['nullable', 'string', 'max:10'],
            'endereco_logradouro' => ['required', 'string', 'max:180'],
            'endereco_numero' => ['nullable', 'string', 'max:20'],
            'endereco_complemento' => ['nullable', 'string', 'max:120'],
            'endereco_bairro' => ['nullable', 'string', 'max:120'],
            'endereco_cidade' => ['required', 'string', 'max:120'],
            'endereco_uf' => ['required', 'string', 'size:2'],
            'endereco_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'endereco_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'endereco_principal' => ['boolean'],
        ]);

        return [
            'type' => $validado['endereco_tipo'],
            'zip_code' => $validado['endereco_cep'] ?? null,
            'street' => $validado['endereco_logradouro'],
            'number' => $validado['endereco_numero'] ?? null,
            'complement' => $validado['endereco_complemento'] ?? null,
            'neighborhood' => $validado['endereco_bairro'] ?? null,
            'city' => $validado['endereco_cidade'],
            'state' => strtoupper($validado['endereco_uf']),
            'latitude' => $validado['endereco_latitude'] ?? null,
            'longitude' => $validado['endereco_longitude'] ?? null,
            'is_primary' => (bool) ($validado['endereco_principal'] ?? false),
        ];
    }
}
