{{--
    Rodapé das telas abertas: uma linha só. A marca aparece uma vez, em tinta
    neutra, e o lado direito traz apenas o que é útil: o que o sistema faz e o
    crédito. Sem avisos técnicos ou lista de capacidades — quem está aqui já tem
    o cabeçalho para andar pela casa.
--}}
<footer class="nf-public-footer">
    <div class="container nf-public-footer-row">
        <span class="nf-brand nf-public-footer-brand">
            <x-ui.brand-mark lado="22" />
            <span class="nf-wordmark nf-wordmark-neutro">
                <span class="nf-wordmark-lead">NEXUS</span><span class="nf-wordmark-tail">-FIELD</span>
            </span>
        </span>

        <p class="nf-public-footer-credit mb-0">
            <span>Gestão de operações em campo</span>
            <span class="nf-signature-sep" aria-hidden="true"></span>
            <x-signature />
        </p>
    </div>
</footer>
