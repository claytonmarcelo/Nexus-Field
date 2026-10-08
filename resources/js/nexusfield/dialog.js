/*
 * Diálogo modal (SweetAlert2) — substitui oficial de alert(), confirm() e
 * prompt(), que estão vetados no projeto.
 */
import Swal from 'sweetalert2';

const BASE = {
    customClass: {
        popup: 'nf-dialog',
    },
    buttonsStyling: true,
    reverseButtons: true,
    showCloseButton: true,
    confirmButtonText: 'Confirmar',
    cancelButtonText: 'Cancelar',
};

/** Promessa que resolve true apenas quando o usuário confirma de fato. */
export function confirmDialog({
    title,
    text = '',
    icon = 'warning',
    confirmText = 'Confirmar',
    cancelText = 'Cancelar',
    danger = false,
}) {
    return Swal.fire({
        ...BASE,
        title,
        text,
        icon,
        iconColor: danger ? undefined : 'var(--nf-primary)',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        customClass: {
            ...BASE.customClass,
            confirmButton: danger ? 'nf-dialog-danger' : '',
        },
    }).then((result) => result.isConfirmed === true);
}

/** Promessa que resolve a resposta digitada, ou null se cancelada. */
export function promptDialog({
    title,
    text = '',
    placeholder = '',
    value = '',
    required = true,
    inputType = 'text',
    confirmText = 'Enviar',
}) {
    return Swal.fire({
        ...BASE,
        title,
        text,
        icon: 'question',
        iconColor: 'var(--nf-primary)',
        showCancelButton: true,
        confirmButtonText: confirmText,
        input: inputType,
        inputPlaceholder: placeholder,
        inputValue: value,
        inputAttributes: {
            autocapitalize: 'sentences',
            'aria-label': text || title,
        },
        preConfirm: (input) => {
            const clean = typeof input === 'string' ? input.trim() : input;

            if (required && !clean) {
                Swal.showValidationMessage('Este campo é obrigatório.');

                return false;
            }

            return clean;
        },
    }).then((result) => (result.isConfirmed ? result.value : null));
}

/** Aviso simples, no lugar do alert() nativo. */
export function infoDialog({ title, text = '', icon = 'info' }) {
    return Swal.fire({
        ...BASE,
        title,
        text,
        icon,
        iconColor: icon === 'error' ? undefined : 'var(--nf-primary)',
        confirmButtonText: 'Entendi',
    });
}
