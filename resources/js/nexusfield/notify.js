/*
 * Notificações de canto (Toastr) — o único caminho para avisos efêmeros.
 * alert() nativo está vetado no projeto.
 */
import toastr from 'toastr';

toastr.options = {
    closeButton: true,
    // O padrão do Toastr injeta HTML. Com dados vindos do usuário isso seria
    // XSS, então o texto é sempre escapado.
    escapeHtml: true,
    tapToDismiss: true,
    closeOnHover: true,
    newestOnTop: true,
    preventDuplicates: true,
    progressBar: true,
    positionClass: 'toast-top-right',
    timeOut: 4500,
    extendedTimeOut: 1200,
    showMethod: 'fadeIn',
    showDuration: 140,
    hideMethod: 'fadeOut',
    hideDuration: 220,
};

function show(type, message, title = '') {
    return toastr[type](message, title);
}

export const toast = {
    success: (message, title = 'Feito') => show('success', message, title),
    error: (message, title = 'Não foi possível') => show('error', message, title),
    warning: (message, title = 'Atenção') => show('warning', message, title),
    info: (message, title = 'Aviso') => show('info', message, title),
};
