<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Address;
use App\Support\StatusCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Endereços são polimórficos desde a fase 2: cliente, técnico e ordem de serviço
 * dividem a mesma tabela, o mesmo formulário (`x-ui.address-fields`) e a mesma
 * validação. O que muda por cadastro é só o dono — que a rota entrega, nunca o
 * payload — e a ficha onde a tela volta depois de gravar.
 */
trait CuidaDeEnderecos
{
    use TrataRegistrosAninhados;

    protected function criarEndereco(Request $request, Model $dono, string $ficha): RedirectResponse
    {
        // O dono da relação vem da rota, nunca do payload: um `addressable_id`
        // enviado pelo formulário carimbaria endereço de um cadastro no vizinho.
        $endereco = $dono->addresses()->create($this->dadosDeEndereco($request));

        $this->garantirUnicoEnderecoPrincipal($dono, $endereco);

        return redirect()
            ->route($ficha, $dono)
            ->with('status', "Endereço de {$endereco->city}/{$endereco->state} adicionado a {$dono->name}.");
    }

    protected function atualizarEndereco(
        Request $request,
        Model $dono,
        Address $endereco,
        string $ficha,
        string $relacao = 'addressable',
    ): RedirectResponse {
        $this->garantirQueEPerecoDoCadastro($dono, $endereco, $relacao);

        $endereco->update($this->dadosDeEndereco($request));
        $this->garantirUnicoEnderecoPrincipal($dono, $endereco);

        return redirect()
            ->route($ficha, $dono)
            ->with('status', "Endereço de {$endereco->city}/{$endereco->state} atualizado.");
    }

    protected function removerEndereco(Model $dono, Address $endereco, string $ficha, string $relacao = 'addressable'): RedirectResponse
    {
        $this->garantirQueEPerecoDoCadastro($dono, $endereco, $relacao);

        $cidade = $endereco->city.'/'.$endereco->state;
        $endereco->delete();

        return redirect()
            ->route($ficha, $dono)
            ->with('status', "Endereço em {$cidade} removido.");
    }

    protected function garantirUnicoEnderecoPrincipal(Model $dono, Address $promovido): void
    {
        if (! $promovido->is_primary) {
            return;
        }

        $dono->addresses()->whereKeyNot($promovido->id)->where('is_primary', true)->update(['is_primary' => false]);
    }

    /** @return array<string, mixed> */
    private function dadosDeEndereco(Request $request): array
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
