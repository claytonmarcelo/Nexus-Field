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

    // A ficha do celular precisa saber o nome de cada coluna antes de alguém
    // chegar perto dela: o rótulo é lido do cabeçalho, não digitado na tela.
    document.querySelectorAll('table.nf-table').forEach(etiquetarCelulas);

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

/**
 * Dá a cada célula o nome da própria coluna, para o CSS de `listings.css`
 * montar a ficha no celular.
 *
 * O texto é lido do `<th>` vivo, e não de um `data-rotulo` digitado em dez
 * telas: renomear uma coluna passaria a exigir a mesma mudança linha a linha
 * abaixo dela, e é assim que um cabeçalho começa a mentir sobre o valor ao
 * lado. Do cabeçalho só interessa a palavra que a pessoa vê — o texto
 * `visually-hidden` que explica a ordenação ao leitor de tela e os ícones
 * ficam de fora da leitura.
 *
 * A ficha só veste a tabela inteira. Uma linha com `colspan` quebraria o
 * alinhamento do resto sem que nada avisasse, e chute de rótulo é pior que a
 * tabela rolante de sempre.
 */
function etiquetarCelulas(tabela) {
    const cabecalhos = tabela.querySelectorAll(':scope > thead > tr');

    if (cabecalhos.length !== 1) {
        return;
    }

    const colunas = [...cabecalhos[0].children];
    const linhas = [...tabela.querySelectorAll(':scope > tbody > tr')];

    if (colunas.length === 0 || linhas.length === 0) {
        return;
    }

    const rotulos = colunas.map((celula) => {
        const texto = celula.cloneNode(true);
        texto.querySelectorAll('.visually-hidden, [aria-hidden="true"]').forEach((n) => n.remove());

        return texto.textContent.replace(/\s+/g, ' ').trim();
    });

    if (linhas.some((linha) => linha.children.length !== rotulos.length)) {
        return;
    }

    linhas.forEach((linha) => {
        [...linha.children].forEach((celula, indice) => {
            if (rotulos[indice] !== '') {
                celula.dataset.rotulo = rotulos[indice];
            }
        });
    });

    tabela.classList.add('nf-table-cards');
}
