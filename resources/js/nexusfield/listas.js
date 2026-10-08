/**
 * Mecânica das listagens filtráveis.
 *
 * `data-nf-autosubmit` num select manda o formulário no instante em que o
 * usuário escolhe, sem exigir o clique em Aplicar. Na caixa de busca o mesmo
 * envio espera 350 ms depois da última tecla: cada letra consultando o MySQL
 * seria mais pesado que útil.
 *
 * Nada aqui é necessário para a tela funcionar. Os campos são um FORM GET de
 * verdade, então sem JavaScript o Aplicar continua enviando, o navegador
 * continua voltando da página de resultado para a listagem com o filtro na
 * query string, e o link de ordenação continua sendo um link.
 */
const ESPERA_BUSCA = 350;

export function init() {
    const tempores = new WeakMap();

    document.addEventListener('change', (event) => {
        const campo = event.target.closest('[data-nf-autosubmit]');

        if (campo?.form) {
            enviar(campo.form);
        }
    });

    document.addEventListener('input', (event) => {
        const campo = event.target.closest('[data-nf-busca]');

        if (!campo?.form) {
            return;
        }

        clearTimeout(tempores.get(campo));
        tempores.set(campo, setTimeout(() => enviar(campo.form), ESPERA_BUSCA));
    });

    // Apagar a busca com o botão "x" do campo é um change em alguns navegadores e
    // um input empty em outros; o submit manual cancela a espera pendente.
    document.addEventListener('submit', (event) => {
        event.target.querySelectorAll?.('[data-nf-busca]').forEach((campo) => {
            clearTimeout(tempores.get(campo));
        });
    });
}

function enviar(form) {
    // page não entra: filtrar de novo é outra consulta, e a página 4 da busca
    // antiga quase nunca existe na busca nova.
    if (form.dataset.nfEnviando === 'true') {
        return;
    }

    form.requestSubmit();
}
