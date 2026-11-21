<x-organization.cadastro-shell
    title="Cadastro de empresa"
    subtitle="Informe os dados da empresa e do administrador. Você receberá um link para confirmar o e-mail."
    :step="1"
>
    <form method="POST" action="{{ route('cadastro.store') }}" data-nf-guard novalidate>
        @csrf

        <h2 class="nf-cadastro-grupo">Empresa</h2>

        <div class="row g-3">
            <div class="col-md-6">
                <x-ui.input label="CNPJ" name="cnpj" required inputmode="numeric" placeholder="00.000.000/0001-00" />
            </div>
            <div class="col-md-6">
                <x-ui.input label="Razão social" name="razao_social" required placeholder="Nome legal da empresa" />
            </div>
            <div class="col-md-6">
                <x-ui.input label="Nome fantasia" name="nome_fantasia" placeholder="Nome usado no dia a dia" />
            </div>
            <div class="col-md-6">
                <x-ui.input label="Inscrição estadual" name="ie" />
            </div>
            <div class="col-md-6">
                <x-ui.input label="Inscrição municipal" name="im" />
            </div>
            <div class="col-md-6">
                <x-ui.input label="CNAE principal" name="cnae_main" placeholder="0000000" />
            </div>
            <div class="col-md-6">
                <x-ui.input label="Cidade" name="cidade" />
            </div>
            <div class="col-md-6">
                <x-ui.input label="UF" name="uf" maxlength="2" placeholder="SP" />
            </div>
        </div>

        <h2 class="nf-cadastro-grupo mt-4">Administrador inicial</h2>

        <div class="row g-3">
            <div class="col-12">
                <x-ui.input label="Nome completo" name="admin_nome" required autocomplete="name" />
            </div>
            <div class="col-md-6">
                <x-ui.input label="E-mail" name="email" type="email" required autocomplete="username" inputmode="email" placeholder="admin@empresa.com.br" />
            </div>
            <div class="col-md-6">
                <x-ui.input label="Telefone" name="telefone" required inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" />
            </div>
        </div>

        <div class="nf-cadastro-termos">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="aceite_termos" id="aceite_termos" value="1" required>
                <label class="form-check-label" for="aceite_termos">Aceito os termos de uso</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="aceite_privacidade" id="aceite_privacidade" value="1" required>
                <label class="form-check-label" for="aceite_privacidade">Aceito a política de privacidade</label>
            </div>
            @error('aceite_termos') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            @error('aceite_privacidade') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        </div>

        <x-ui.button type="submit" variant="primary" size="lg" wide icon="fa-solid fa-arrow-right">
            Continuar
        </x-ui.button>
    </form>

    <p class="nf-auth-back">
        <a href="{{ route('dashboard') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Voltar ao painel</a>
    </p>
</x-organization.cadastro-shell>
