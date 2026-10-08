/**
 * Confirmação de ação em formulário. Substitui o `window.confirm` nativo — que o
 * projeto proíbe — pela caixa do design system (SweetAlert2 com as cores do
 * tema), e só devolve o envio depois do "sim" do usuário.
 *
 * O contrato é o `data-nf-confirm` no FORM, mais três atributos de texto. Sem
 * JavaScript nada é bloqueado: o formulário segue para o servidor, onde a
 * permissão decide se a ação acontece.
 */
import { confirmDialog } from './dialog';

export function init() {
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form[data-nf-confirm]');

        if (!form || form.dataset.nfConfirmado === 'true') {
            return;
        }

        event.preventDefault();

        const botao = form.querySelector('button[type="submit"]');

        confirmDialog({
            title: form.dataset.confirmTitulo || 'Confirmar ação',
            text: form.dataset.confirmTexto || '',
            confirmText: form.dataset.confirmRotulo || 'Confirmar',
            danger: form.dataset.confirmPerigo !== 'false',
        }).then((aceito) => {
            if (!aceito) {
                return;
            }

            form.dataset.nfConfirmado = 'true';
            form.requestSubmit(botao ?? undefined);
        });
    });

    // Volta pelo botão do navegador: o flag de "já confirmado" precisa sumir, ou
    // a segunda confirmação da mesma linha seria pulada.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('form[data-nf-confirm]').forEach((form) => {
            delete form.dataset.nfConfirmado;
        });
    });
}
