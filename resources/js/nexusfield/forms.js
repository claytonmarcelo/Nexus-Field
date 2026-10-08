/*
 * Guarda de envio: um clique, uma request. Evita duplicar cadastro quando o
 * usuário clica duas vezes no mesmo botão enquanto o servidor responde.
 */
function setState(button, loading) {
    if (!button) {
        return;
    }

    button.dataset.loading = loading ? 'true' : 'false';
    button.disabled = loading;
    button.setAttribute('aria-busy', loading ? 'true' : 'false');
    button.classList.toggle('nf-btn-spinner', loading);
}

export function init() {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-nf-guard]');

        if (!form || form.dataset.nfEnviando === 'true') {
            return;
        }

        // O navegador já bloqueia o envio e mostra os erros de validação.
        if (!form.reportValidity()) {
            return;
        }

        form.dataset.nfEnviando = 'true';
        setState(form.querySelector('button[type="submit"], input[type="submit"]'), true);
    });

    // Voltar pela histórico (bfcache) traria o botão travado.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-nf-guard]').forEach((form) => {
            delete form.dataset.nfEnviando;
            setState(form.querySelector('button[type="submit"], input[type="submit"]'), false);
        });
    });
}
