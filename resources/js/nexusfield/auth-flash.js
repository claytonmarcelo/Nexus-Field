/*
 * Avisos das telas de acesso sem Toastr/jQuery.
 *
 * O bundle completo usa Toastr para mostrar `session('status')` e erros. Nas
 * páginas de entrada isso custaria centenas de KB só para um quadrado de texto.
 * Aqui o mesmo conteúdo já renderizado pelo Blade ganha uma caixa flutuante com
 * estilo próprio — nenhum HTML vindo do servidor é interpretado como marcação.
 */

const TONS = {
    success: { classe: 'nf-auth-aviso-sucesso', titulo: 'Feito' },
    error: { classe: 'nf-auth-aviso-erro', titulo: 'Não foi possível' },
    warning: { classe: 'nf-auth-aviso-aviso', titulo: 'Atenção' },
    info: { classe: 'nf-auth-aviso-info', titulo: 'Aviso' },
};

export function init() {
    const avisos = [...document.querySelectorAll('[data-nf-flash]')];

    if (avisos.length === 0) {
        return;
    }

    const area = document.createElement('div');
    area.className = 'nf-auth-avisos';
    area.setAttribute('role', 'status');
    area.setAttribute('aria-live', 'polite');

    for (const no of avisos) {
        const tom = TONS[no.dataset.nfFlash] ?? TONS.info;
        const caixa = document.createElement('div');
        caixa.className = `nf-auth-aviso ${tom.classe}`;

        const titulo = document.createElement('strong');
        titulo.textContent = tom.titulo;

        const texto = document.createElement('span');
        texto.textContent = no.textContent.trim();

        caixa.append(titulo, texto);
        area.append(caixa);
        no.remove();
    }

    document.body.append(area);
}
