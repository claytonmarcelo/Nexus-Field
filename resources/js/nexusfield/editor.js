/*
 * Editor de texto rico dos textos longos do chamado: a descrição do problema e a
 * nota da conversa. Summernote é UMD e procura `window.jQuery` quando é avaliado,
 * por isso este módulo entra depois de jquery-global no app.js.
 *
 * A área de texto original continua sendo o campo do formulário: o editor escreve
 * nela a cada mudança e também no instante do envio. Sem JavaScript o usuário
 * continua vendo e digitando numa `textarea` comum, com o mesmo nome de campo, e o
 * servidor recebe o texto do mesmo jeito.
 */
import $ from 'jquery';
import 'summernote/dist/summernote-bs5.min.js';

// Só o que o sanitizador do servidor preserva. Cor, tamanho de letra, imagem e
// tabela ficariam bonitos aqui e sumiriam na tela de quem lê — botão que mente é
// pior que botão ausente.
const FERRAMENTAS = [
    ['texto', ['bold', 'italic', 'underline', 'strikethrough']],
    ['lista', ['ul', 'ol']],
    ['inserir', ['link']],
    ['limpar', ['clear']],
];

function opcoes(campo) {
    return {
        toolbar: FERRAMENTAS,
        buttons: {},
        styleWithSpan: false,
        placeholder: campo.dataset.nfEditorPlaceholder || '',
        height: Number(campo.dataset.nfEditorAltura || 220),
        minHeight: 140,
        dialogsInBody: true,
        disableDragAndDrop: true,
        popover: {
            air: [],
            link: [['link', ['unlink', 'rel']]],
            image: [],
        },
        shortcuts: false,
    };
}

export function init() {
    const campos = () => document.querySelectorAll('textarea[data-nf-editor]');

    campos().forEach((campo) => {
        if (campo.dataset.nfEditorPronto === 'true') {
            return;
        }

        campo.dataset.nfEditorPronto = 'true';

        // `required` numa caixa que o editor esconde produz o erro que o navegador
        // não sabe mostrar: "invalid form control is not focusable". A obrigatoriedade
        // é do servidor, que também recusa o parágrafo vazio.
        campo.removeAttribute('required');

        $(campo).summernote(opcoes(campo));
    });

    $(document).on('summernote.change', 'textarea[data-nf-editor]', function () {
        this.value = $(this).summernote('code');
    });

    document.addEventListener('submit', (event) => {
        event.target.querySelectorAll?.('textarea[data-nf-editor]').forEach((campo) => {
            if (campo.dataset.nfEditorPronto === 'true') {
                campo.value = $(campo).summernote('code');
            }
        });
    });
}
