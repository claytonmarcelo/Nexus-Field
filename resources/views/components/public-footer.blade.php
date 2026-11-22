{{--
    Rodapé das telas abertas: a marca e o direito autoral à esquerda, o crédito à
    direita — a mesma distribuição do site de referência. Nada de aviso técnico ou
    lista de capacidades: quem está aqui já tem o cabeçalho para andar pela casa.
--}}
<footer class="nf-public-footer">
    <div class="container nf-public-footer-row">
        <div class="nf-public-footer-left">
            <span class="nf-brand nf-public-footer-brand">
                <x-ui.brand-mark lado="22" />
                <span class="nf-wordmark nf-wordmark-neutro">
                    <span class="nf-wordmark-lead">NEXUS</span><span class="nf-wordmark-tail">-FIELD</span>
                </span>
            </span>
            <span class="nf-public-footer-copyright">© {{ now()->format('Y') }} NEXUS-FIELD</span>
        </div>

        <p class="nf-public-footer-credit mb-0">Desenvolvido por Clayton Marcelo</p>
    </div>
</footer>
