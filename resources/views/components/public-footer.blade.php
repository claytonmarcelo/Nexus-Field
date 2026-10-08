{{--
    Rodapé das telas abertas: uma linha só, com a marca de um lado e o crédito do
    outro. Sem lista de capacidades nem atalho de navegação — quem está numa tela
    aberta já tem o cabeçalho para andar pela casa.
--}}
<footer class="nf-public-footer">
    <div class="container nf-public-footer-row">
        <span class="nf-brand">
            <x-ui.brand-mark />
            <span class="nf-wordmark">
                <span class="nf-wordmark-lead">NEXUS</span><span class="nf-wordmark-tail">-FIELD</span>
            </span>
        </span>

        <p class="nf-public-footer-credit mb-0">
            <x-signature />
            <span class="nf-footer-note">Acesso autenticado · dados isolados por empresa</span>
        </p>
    </div>
</footer>
