/*
 * Mostrar / ocultar senha.
 *
 * O botão só troca o `type` do próprio campo: nada de espelhar o valor em outro
 * lugar, nada de gravar em localStorage. Voltar para `password` no blur não é
 * feito porque o usuário costuma conferir a senha e depois corrigir um caractere
 * — esconder no meio disso seria pior.
 */
const LABELS = {
    shown: 'Ocultar senha',
    hidden: 'Mostrar senha',
};

function syncState(button, field) {
    const visible = field.type === 'text';

    button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    button.setAttribute('aria-label', visible ? LABELS.shown : LABELS.hidden);
    button.title = visible ? LABELS.shown : LABELS.hidden;
}

export function init() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-nf-password-toggle]');

        if (!button) {
            return;
        }

        event.preventDefault();

        const field = document.getElementById(button.getAttribute('aria-controls'));

        if (!field) {
            return;
        }

        // Mudar o type recria o controle e joga o cursor para o fim; o
        // intervalo anterior volta junto com o foco.
        const inicio = field.selectionStart;
        const fim = field.selectionEnd;

        field.type = field.type === 'password' ? 'text' : 'password';
        syncState(button, field);

        field.focus();
        field.setSelectionRange(inicio, fim);
    });

    // Campos reidratados pelo navegador (bfcache) chegam com o type salvo e o
    // rótulo do botão desalinhado.
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-nf-password-toggle]').forEach((button) => {
            const field = document.getElementById(button.getAttribute('aria-controls'));

            if (field) {
                syncState(button, field);
            }
        });
    });
}
