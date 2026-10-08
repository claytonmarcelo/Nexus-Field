{{--
    Rodapé do layout autenticado. A estrutura é do AdminLTE 4; a assinatura é a
    mesma da página pública, para o crédito não viver em dois lugares. O lado
    direito diz onde a sessão está, e isso vem do banco — não de texto fixo.
--}}
@php($empresa = auth()->user()?->company?->name)

<footer class="app-footer">
    <div class="container-fluid nf-content">
        <div class="nf-footer-row">
            <x-signature />

            <p class="mb-0 nf-footer-meta">
                <span class="nf-footer-chip nf-footer-chip-tenant">
                    {{ $empresa ?? 'Sem empresa vinculada' }}
                </span>
                <span class="nf-footer-note">Gestão de operações em campo</span>
            </p>
        </div>
    </div>
</footer>
