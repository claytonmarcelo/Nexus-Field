/*
 * A agenda desenhada sobre FullCalendar 6.
 *
 * O quadro é uma janela de tempo lida do banco: cada vez que o usuário muda de mês,
 * de semana ou de dia, o calendário pede o intervalo que está na tela e o servidor
 * responde com os compromissos e as ordens agendadas daquela conta — alcance e
 * permissão resolvidos antes de qualquer evento virar JSON.
 *
 * O relógio é de parede, não de fuso: `APP_TIMEZONE` é America/Sao_Paulo e o MySQL
 * atende com o mesmo sistema, então as datas viajam e voltam sem offset. Se o
 * JavaScript mandasse ISO com `Z`, a janela de um técnico de São Paulo chegaria
 * três horas mais cedo no servidor.
 */
import { Calendar } from 'fullcalendar';
import ptBr from '@fullcalendar/core/locales/pt-br';
import { toast } from './notify';

const HORA_DE_ABRIR_A_JANELA = 9;
const DURACAO_PADRAO_DA_JANELA = 1;

export function init() {
    document.querySelectorAll('[data-nf-agenda]').forEach(montar);
}

function montar(elemento) {
    const feed = elemento.dataset.feed;
    const rotaNova = elemento.dataset.nova;
    const podeCriar = elemento.dataset.criar === '1';
    const csrf = elemento.dataset.csrf;
    const filtros = lerFiltros(elemento.dataset.filtro);

    // O esqueleto é irmão do quadro, não filho: o FullCalendar toma o
    // elemento onde monta e apagaria qualquer faixa deixada dentro dele.
    const esqueleto = elemento.parentElement?.querySelector('[data-nf-esqueleto]');

    const calendario = new Calendar(elemento, {
        locales: [ptBr],
        locale: 'pt-br',
        initialView: window.innerWidth < 768 ? 'listWeek' : 'dayGridMonth',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
        },
        buttonText: { today: 'Hoje', month: 'Mês', week: 'Semana', day: 'Dia', list: 'Lista' },
        slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
        nowIndicator: true,
        // Nadar para a manhã do dia de trabalho sem cortar ninguém: a faixa visível
        // começa cedo, mas o quadro continua desenhando a madrugada inteira.
        scrollTime: '07:30:00',
        dayMaxEventRows: 4,
        dayMaxEvents: 4,
        stickyHeaderDates: true,
        noEventsText: 'Nenhum compromisso nem ordem agendada nesta janela.',
        loading: (buscando) => mostrarEsqueleto(esqueleto, elemento, buscando),
        events: (info) => buscarEventos(info, feed, filtros),
        dateClick: (info) => abrirFormulario(info, podeCriar, rotaNova),
        eventDrop: (info) => remanejar(info, calendario, csrf),
        eventResize: (info) => remanejar(info, calendario, csrf),
    });

    calendario.render();
}

/**
 * O único carregamento desta tela é a janela de tempo que o servidor ainda não
 * devolveu. Enquanto ela chega, o quadro esmaece e a faixa de esqueleto aparece;
 * `aria-busy` no elemento que tem `role="application"` é o aviso para quem usa
 * leitor de tela de que o conteúdo ali ainda vai mudar. Sem isso, a espera seria
 * silêncio: a mesma cara de uma agenda vazia.
 */
function mostrarEsqueleto(esqueleto, elemento, buscando) {
    if (esqueleto) {
        esqueleto.hidden = !buscando;
    }

    elemento.classList.toggle('is-carregando', buscando);
    elemento.setAttribute('aria-busy', buscando ? 'true' : 'false');
}

/**
 * Um só caminho de rede para os dois pedidos da tela — a leitura da janela e a
 * escrita do arrasto. Cabeçalho, corpo, CSRF e a tradução do erro moram aqui; quem
 * chama decide o que fazer com o resultado, não como pedir. Eram dois blocos de
 * `fetch` quase idênticos, e bloco quase idêntico é o jeito mais rápido de uma
 * mensagem de erro ficar certa num botão e errada no outro.
 *
 * Os dois tipos de falha são distintos de propósito: `Recusa` é o servidor dizendo
 * não (mensagem dele, janela que não muda), `SemConexao` é o servidor que não
 * respondeu (o quadro pode até continuar mostrando o que já tinha).
 */
class Recusa extends Error {
    constructor(mensagem, status) {
        super(mensagem);
        this.status = status;
    }
}

class SemConexao extends Error {}

async function pedido(url, { method = 'GET', corpo = null, csrf = null } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };

    if (corpo !== null) {
        headers['Content-Type'] = 'application/json';
    }

    if (csrf) {
        headers['X-CSRF-TOKEN'] = csrf;
    }

    let resposta;

    try {
        resposta = await fetch(url, {
            method,
            headers,
            credentials: 'same-origin',
            body: corpo === null ? undefined : JSON.stringify(corpo),
        });
    } catch (erro) {
        throw new SemConexao(erro?.message || 'sem resposta');
    }

    const dados = await resposta.json().catch(() => null);

    if (!resposta.ok) {
        throw new Recusa(mensagemDaRecusa(dados), resposta.status);
    }

    return dados;
}

/** A recusa que chega em JSON é a palavra do servidor; a que não chega é a da casa. */
function mensagemDaRecusa(dados) {
    if (dados?.mensagem) {
        return dados.mensagem;
    }

    // Validação da Laravel devolve os campos; o que o usuário precisa ler é o primeiro.
    const primeiro = dados?.errors && Object.values(dados.errors)[0]?.[0];

    return primeiro || 'O servidor recusou a consulta da agenda.';
}

async function buscarEventos(info, feed, filtros) {
    const parametros = new URLSearchParams({
        inicio: parede(info.start),
        fim: parede(info.end),
        ...filtros,
    });

    try {
        return await pedido(`${feed}?${parametros}`);
    } catch (erro) {
        if (erro instanceof Recusa) {
            toast.error(erro.message, 'A agenda não abriu');

            return [];
        }

        toast.error('A consulta ao calendário não respondeu. Recarregue a tela.', 'Sem conexão');
        throw erro;
    }
}

/**
 * Arrastar e soltar é a única escrita nesta tela, e ela passa pelo servidor: a rota
 * confere alcance, estado e janela antes de gravar hora. Se o servidor recusa, o
 * evento volta ao lugar de onde saiu — calendário que aceita o que o banco não
 * aceitou é a maneira mais rápida de mentir para quem lê a escala.
 *
 * Enquanto o PATCH não volta, o compromisso arrastado fica esmaecido: o dedo já
 * soltou, o banco ainda não disse sim, e essa diferença precisa aparecer.
 */
async function remanejar(info, calendario, csrf) {
    const compromisso = info.event.extendedProps;

    if (compromisso.natureza !== 'compromisso') {
        info.revert();
        toast.info('A janela de uma ordem é movida na ficha dela, onde o motivo fica registrado.');

        return;
    }

    const corpo = { inicio: parede(info.event.start) };

    if (info.event.end) {
        // FullCalendar trata o fim de um evento de dia inteiro como exclusivo; o
        // banco guarda o último dia. Tira o dia que o quadro emprestou.
        corpo.fim = parede(
            compromisso.diaInteiro
                ? new Date(info.event.end.getTime() - 864e5)
                : info.event.end,
        );
    }

    gravando(info, true);

    try {
        const dados = await pedido(compromisso.janela, { method: 'PATCH', corpo, csrf });
        toast.success(`Nova janela: ${dados?.compromisso?.janela ?? 'gravada no servidor'}.`);
    } catch (erro) {
        info.revert();

        if (erro instanceof Recusa) {
            toast.error(erro.message, 'A janela não mudou');

            return;
        }

        toast.error('O servidor não respondeu ao remanejamento. A janela voltou ao lugar.', 'Sem conexão');
        throw erro;
    } finally {
        gravando(info, false);
    }

    // Relê a janela: o servidor pode ter deslocado os dias inteiros, e o quadro tem
    // de mostrar o que está gravado, não o que o dedo soltou.
    calendario.refetchEvents();
}

/** A marca de escrita no evento que está no ar, e só nele — o resto do quadro segue vivo. */
function gravando(info, ativa) {
    info.el?.classList.toggle('is-gravando', ativa);
}

/** Clicar num dia vazio é começar a remarcar: a janela escolhida vai na URL do formulário. */
function abrirFormulario(info, podeCriar, rotaNova) {
    if (!podeCriar || !rotaNova) {
        return;
    }

    const url = new URL(rotaNova, window.location.origin);

    if (info.allDate) {
        const dia = parede(info.date).slice(0, 10);
        url.searchParams.set('inicio', `${dia}T${String(HORA_DE_ABRIR_A_JANELA).padStart(2, '0')}:00:00`);
        url.searchParams.set('fim', `${dia}T${String(HORA_DE_ABRIR_A_JANELA + DURACAO_PADRAO_DA_JANELA).padStart(2, '0')}:00:00`);
    } else {
        url.searchParams.set('inicio', parede(info.start));
        url.searchParams.set('fim', parede(info.end));
    }

    window.location.href = url;
}

function lerFiltros(bruto) {
    try {
        const lido = JSON.parse(bruto || '{}');

        return Object.fromEntries(Object.entries(lido).filter(([, valor]) => valor !== ''));
    } catch (erro) {
        return {};
    }
}

/** Data em hora de parede, sem fuso — o mesmo formato que o servidor escreve. */
function parede(momento) {
    const duas = (numero) => String(numero).padStart(2, '0');

    return `${momento.getFullYear()}-${duas(momento.getMonth() + 1)}-${duas(momento.getDate())}`
        + `T${duas(momento.getHours())}:${duas(momento.getMinutes())}:${duas(momento.getSeconds())}`;
}
