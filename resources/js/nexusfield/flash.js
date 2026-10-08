/*
 * Recados do servidor viram toast. O Blade entrega o texto escondido em
 * [data-nf-flash] e este módulo o lê uma única vez por carregamento de página.
 */
import { toast } from './notify';

export function init() {
    document.querySelectorAll('[data-nf-flash]').forEach((node) => {
        const message = node.textContent.trim();
        const notify = toast[node.dataset.nfFlash];

        if (message && notify) {
            notify(message);
        }

        node.remove();
    });
}
