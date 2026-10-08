{{-- Campos do contato dentro da ficha do cliente. Os nomes vêm prefixados porque
     o formulário de endereço divide a mesma tela e a mesma caixa de erros. --}}
@props(['contato'])

<div class="nf-form-grade">
    <x-ui.input label="Nome" name="contato_nome" :value="$contato->name"
        placeholder="Quem atende do lado do cliente" required />

    <x-ui.input label="Cargo" name="contato_cargo" :value="$contato->role"
        placeholder="Encarregado, síndico, TI..." />

    <x-ui.input label="E-mail" name="contato_email" type="email" :value="$contato->email"
        placeholder="nome@cliente.com.br" autocomplete="off" />

    <x-ui.input label="Telefone" name="contato_telefone" :value="$contato->phone" inputmode="tel"
        placeholder="(11) 90000-0000" />
</div>

<x-ui.switch label="Contato principal" name="contato_principal" :checked="$contato->is_primary"
    hint="É quem recebe o aviso de ordem de serviço. Só um por cliente." />
