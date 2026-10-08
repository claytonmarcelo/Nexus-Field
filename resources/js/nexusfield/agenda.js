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
        events: (info) => buscarEventos(info, feed, filtros),
        dateClick: (info) => abrirFormulario(info, podeCriar, rotaNova),
        eventDrop: (info) => remanejar(info, calendario, csrf),
        eventResize: (info) => remanejar(info, calendario, csrf),
    });

    calendario.render();
}

async function buscarEventos(info, feed, filtros) {
    const parametros = new URLSearchParams({
        inicio: parede(info.start),
        fim: parede(info.end),
        ...filtros,
    });

    try {
        const resposta = await fetch(`${feed}?${parametros}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        if (!resposta.ok) {
            toast.error(await mensagemDaResposta(resposta), 'A agenda não abriu');

            return [];
        }

        return await resposta.json();
    } catch (erro) {
        toast.error('A consulta ao calendário não respondeu. Recarregue a tela.', 'Sem conexão');
        throw erro;
    }
}

/**
 * Arrastar e soltar é a única escrita nesta tela, e ela passa pelo servidor: a rota
 * confere alcance, estado e janela antes de gravar hora. Se o servidor recusa, o
 * evento volta ao lugar de onde saiu — calendário que aceita o que o banco não
 * aceitou é a maneira mais rápida de mentir para quem lê a escala.
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

    try {
        const resposta = await fetch(compromisso.janela, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(corpo),
        });

        if (!resposta.ok) {
            info.revert();
            toast.error(await mensagemDaResposta(resposta), 'A janela não mudou');

            return;
        }

        const dados = await resposta.json();
        toast.success(`Nova janela: ${dados.compromisso.janela}.`);
    } catch (erro) {
        info.revert();
        toast.error('O servidor não respondeu ao remanejamento. A janela voltou ao lugar.', 'Sem conexão');
        throw erro;
    }

    // Relê a janela: o servidor pode ter deslocado os dias inteiros, e o quadro tem
    // de mostrar o que está gravado, não o que o dedo soltou.
    calendario.refetchEvents();
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

async function mensagemDaResposta(resposta) {
    const corpo = await resposta.json().catch(() => null);

    if (corpo?.mensagem) {
        return corpo.mensagem;
    }

    // Validação da Laravel devolve os campos; o que o usuário precisa ler é o primeiro.
    const primeiro = corpo?.errors && Object.values(corpo.errors)[0]?.[0];

    return primeiro || 'O servidor recusou a consulta da agenda.';
}
