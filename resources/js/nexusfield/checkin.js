/*
 * A coordenada do check-in, lida no aparelho quando alguém pede.
 *
 * Este módulo escreve latitude e longitude em dois campos ocultos e nada mais. A
 * hora não passa por aqui de propósito: `checkin_at` e `checkout_at` nascem do
 * relógio do servidor, porque hora lida do celular daria a quem tem interesse
 * direto no resultado o poder de escolher o horário em que diz ter chegado.
 *
 * A distância que aparece aqui é prévia. O número que vira registro é medido no
 * servidor, entre a posição que ele acabou de receber e o endereço gravado na
 * ordem, e as duas leituras podem divergir por um motivo honesto: o técnico se
 * moveu entre apertar "ler posição" e apertar "registrar chegada".
 *
 * Permissão recusada, aparelho sem GPS e tempo esgotado não bloqueiam o envio. O
 * formulário segue sem as duas coordenadas e o banco grava a visita como sem
 * posição, que é a verdade da hora — um bloqueio aqui ensinaria o técnico a
 * inventar coordenada só para conseguir registrar a chegada.
 */

import { toast } from './notify';

const SEGUNDOS_DA_LEITURA = 12;

export function init() {
    document.querySelectorAll('[data-nf-checkin]').forEach(montar);
}

function montar(form) {
    const latitude = form.querySelector('[data-nf-lat]');
    const longitude = form.querySelector('[data-nf-lon]');
    const leitura = form.querySelector('[data-nf-leitura]');
    const botao = form.querySelector('[data-nf-ler]');

    if (!latitude || !longitude || !leitura || !botao) {
        return;
    }

    if (!('geolocation' in navigator)) {
        mostrar(leitura, 'Este navegador não reporta posição ao aparelho. O registro sai marcado como sem posição.', 'aviso');
        botao.disabled = true;

        return;
    }

    botao.addEventListener('click', () => {
        botao.disabled = true;
        mostrar(leitura, 'Lendo a posição do aparelho...', 'lendo');

        navigator.geolocation.getCurrentPosition(
            (posicao) => {
                latitude.value = posicao.coords.latitude.toFixed(7);
                longitude.value = posicao.coords.longitude.toFixed(7);

                mostrar(leitura, textoDaLeitura(form, posicao), 'ok');
                botao.disabled = false;
            },
            (erro) => {
                latitude.value = '';
                longitude.value = '';

                mostrar(leitura, `Sem posição: ${motivo(erro)}. O registro sai marcado como sem posição.`, 'aviso');
                botao.disabled = false;
            },
            { enableHighAccuracy: true, timeout: SEGUNDOS_DA_LEITURA * 1000, maximumAge: 0 },
        );
    });

    // A posição lida vale para o instante em que foi lida. Deixar o técnico enviar
    // uma leitura de vinte minutos atrás, com o carro já na rua seguinte, é gravar
    // uma coordenada que não descreve o ato.
    form.addEventListener('submit', (evento) => {
        const lidaEm = Number(form.dataset.lidaEm);

        if (latitude.value === '' || !Number.isFinite(lidaEm)) {
            return;
        }

        if (Date.now() - lidaEm > 3 * 60 * 1000) {
            evento.preventDefault();
            botao.disabled = false;
            toast.warning('A posição lida tem mais de três minutos. Leia de novo antes de registrar.', 'Posição velha');
        }
    });
}

function textoDaLeitura(form, posicao) {
    form.dataset.lidaEm = String(Date.now());

    const horas = new Date(posicao.timestamp).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    const precisao = Math.round(posicao.coords.accuracy);
    const enderecos = [Number(form.dataset.latOrdem), Number(form.dataset.lonOrdem)];

    const base = `Lida às ${horas}: ${posicao.coords.latitude.toFixed(6)}, ${posicao.coords.longitude.toFixed(6)} (±${precisao} m).`;

    if (!enderecos.every(Number.isFinite)) {
        return `${base} O endereço desta ordem não tem coordenada, então não há distância a medir.`;
    }

    const metros = Math.round(haversineMetros(
        posicao.coords.latitude,
        posicao.coords.longitude,
        enderecos[0],
        enderecos[1],
    ));

    const raio = Number(form.dataset.raio);
    const dentro = Number.isFinite(raio) && metros <= raio;

    return `${base} Prévia: ${metros} m do endereço — ${dentro ? 'dentro' : 'fora'} do raio de ${raio} m. A medida que fica é a do servidor.`;
}

function mostrar(elemento, texto, tom) {
    elemento.textContent = texto;
    elemento.dataset.tom = tom;
}

function motivo(erro) {
    const motivos = {
        1: 'a permissão de localização foi recusada',
        2: 'o sinal de posicionamento não chegou',
        3: 'a leitura demorou demais',
    };

    return motivos[erro.code] || 'o aparelho não respondeu';
}

/**
 * Prévia desenhada só para o técnico conferir o ponteiro antes de apertar. A conta
 * que vale é a de `App\Support\Distancia`, feita no servidor com o endereço que
 * está no banco — se as duas discordam, é a de lá que vira registro.
 */
function haversineMetros(latUm, lonUm, latDois, lonDois) {
    const raioDaTerra = 6_371_000;
    const paraRadiano = Math.PI / 180;
    const deltaLat = (latDois - latUm) * paraRadiano;
    const deltaLon = (lonDois - lonUm) * paraRadiano;

    const metade = Math.sin(deltaLat / 2) ** 2
        + Math.cos(latUm * paraRadiano) * Math.cos(latDois * paraRadiano) * Math.sin(deltaLon / 2) ** 2;

    return raioDaTerra * 2 * Math.atan2(Math.sqrt(metade), Math.sqrt(1 - metade));
}
