<?php

namespace App\Http\Controllers\Technicians;

use App\Http\Controllers\Concerns\CuidaDeEnderecos;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Technician;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Base do técnico na mesma tabela polimórfica do endereço de cliente: o dono vem
 * da rota (`tecnicos/{tecnico}/enderecos`) e a validação é a compartilhada em
 * `CuidaDeEnderecos`. É daqui que a fase 15 compara a distância do check-in.
 */
class TechnicianAddressController extends Controller
{
    use CuidaDeEnderecos;

    public function store(Request $request, Technician $tecnico): RedirectResponse
    {
        return $this->criarEndereco($request, $tecnico, 'technicians.show');
    }

    public function update(Request $request, Technician $tecnico, Address $endereco): RedirectResponse
    {
        return $this->atualizarEndereco($request, $tecnico, $endereco, 'technicians.show');
    }

    public function destroy(Technician $tecnico, Address $endereco): RedirectResponse
    {
        return $this->removerEndereco($tecnico, $endereco, 'technicians.show');
    }
}
