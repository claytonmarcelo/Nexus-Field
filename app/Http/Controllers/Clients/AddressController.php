<?php

namespace App\Http\Controllers\Clients;

use App\Http\Controllers\Concerns\CuidaDeEnderecos;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Endereços do cliente. A ficha tem dois formulários dividindo a mesma caixa de
 * erros, por isso os campos vêm prefixados (`endereco_*`); a validação, o
 * endereço principal único e a volta para a ficha moram em `CuidaDeEnderecos`,
 * que o técnico da fase 10 e a ordem de serviço reutilizam.
 */
class AddressController extends Controller
{
    use CuidaDeEnderecos;

    public function store(Request $request, Client $cliente): RedirectResponse
    {
        return $this->criarEndereco($request, $cliente, 'clients.show');
    }

    public function update(Request $request, Client $cliente, Address $endereco): RedirectResponse
    {
        return $this->atualizarEndereco($request, $cliente, $endereco, 'clients.show');
    }

    public function destroy(Client $cliente, Address $endereco): RedirectResponse
    {
        return $this->removerEndereco($cliente, $endereco, 'clients.show');
    }
}
