/*
 * O botão do menu lateral não decide nada: ele traduz.
 *
 * Quem fecha e abre o menu é o AdminLTE 4, e o estado mora no <body> —
 * `sidebar-collapse` no desktop, `sidebar-open` no celular. Manter uma segunda
 * fonte de verdade aqui seria prometer que o botão sabe de algo que ele apenas
 * empresta, então este módulo só lê a classe do corpo e escreve `aria-expanded`
 * e o rótulo no próprio botão.
 *
 * O observador existe porque o menu também fecha sem toque no botão: no celular,
 * bater fora da faixa fecha o painel e Esc idem. Qualquer um desses caminhos mexe
 * na classe do corpo, e é a classe do corpo que o leitor de tela precisa ver
 * refletida no controle que abriu aquilo.
 */

const LARGO_OUFERA = '(min-width: 992px)';

export function init() {
    const botoes = document.querySelectorAll('[data-nf-menu-toggle]');

    if (botoes.length === 0) {
        return;
    }

    const consulta = window.matchMedia(LARGO_OUFERA);

    const sincronizar = () => {
        // Aberto é o painel visível: no desktop ele nasce aberto e só fecha com
        // `sidebar-collapse`; no celular ele só existe enquanto o corpo carregar
        // `sidebar-open`.
        const aberto = consulta.matches
            ? !document.body.classList.contains('sidebar-collapse')
            : document.body.classList.contains('sidebar-open');

        const rotulo = aberto ? 'Recolher o menu lateral' : 'Expandir o menu lateral';

        for (const botao of botoes) {
            botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
            botao.setAttribute('aria-label', rotulo);
            botao.setAttribute('title', rotulo);
        }
    };

    sincronizar();

    new MutationObserver(sincronizar).observe(document.body, {
        attributes: true,
        attributeFilter: ['class'],
    });

    if (typeof consulta.addEventListener === 'function') {
        consulta.addEventListener('change', sincronizar);
    }
}
