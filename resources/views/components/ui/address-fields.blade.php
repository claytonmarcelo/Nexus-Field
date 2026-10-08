{{-- Campos do endereço dentro da ficha. Serve para outro cadastro no mesmo shape
     (técnico, ordem de serviço), porque a tabela `addresses` é polimórfica e o
     check-in de campo lê latitude/longitude daqui. --}}
@props([
    'endereco',
    'tipos' => [],
])

<div class="nf-form-grade">
    <x-ui.select label="Tipo" name="endereco_tipo" :opcoes="$tipos" :value="$endereco->type" required />

    <x-ui.input label="CEP" name="endereco_cep" :value="$endereco->zip_code" inputmode="numeric"
        placeholder="00000-000" />

    <div class="nf-form-largo">
        <x-ui.input label="Logradouro" name="endereco_logradouro" :value="$endereco->street"
            placeholder="Rua, avenida ou estrada como está no contrato" required />
    </div>

    <x-ui.input label="Número" name="endereco_numero" :value="$endereco->number" />

    <x-ui.input label="Complemento" name="endereco_complemento" :value="$endereco->complement"
        placeholder="Sala, andar, bloco" />

    <x-ui.input label="Bairro" name="endereco_bairro" :value="$endereco->neighborhood" />

    <x-ui.input label="Cidade" name="endereco_cidade" :value="$endereco->city" required />

    <x-ui.input label="UF" name="endereco_uf" :value="$endereco->state" maxlength="2"
        placeholder="SP" hint="Duas letras; é o que aparece na ordem de serviço." />

    <x-ui.input label="Latitude" name="endereco_latitude" type="number" :value="$endereco->latitude"
        step="0.0000001" min="-90" max="90" placeholder="-23.5505200" inputmode="decimal" />

    <x-ui.input label="Longitude" name="endereco_longitude" type="number" :value="$endereco->longitude"
        step="0.0000001" min="-180" max="180" placeholder="-46.6333080" inputmode="decimal" />
</div>

<x-ui.switch label="Endereço principal" name="endereco_principal" :checked="$endereco->is_primary"
    hint="É deste endereço que o painel e a ordem de serviço mostram a localização." />
