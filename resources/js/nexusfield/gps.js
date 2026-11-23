/*
 * Mapa do campo. O servidor manda a posição e a rota já recortadas pelo alcance
 * do usuário; aqui elas só viram desenho. O traçado é a sequência de chegadas e
 * saídas registradas — nada é interpolado, nada é inventado entre dois pontos.
 *
 * O compartilhamento de posição usa a permissão do navegador como consentimento
 * e só escreve na ficha de quem está logado: o endpoint não aceita id alheio.
 */
import L from 'leaflet';

const CORES = ['#2563eb', '#0f9d8a', '#b45309', '#7c3aed', '#be123c', '#0891b2'];
const INTERVALO_MINIMO_MS = 30000;

function texto(valor) {
    return document.createTextNode(valor ?? '—');
}

function linha(rotulo, valor) {
    const par = document.createElement('div');
    const forte = document.createElement('strong');
    forte.appendChild(texto(rotulo));
    par.appendChild(forte);
    par.appendChild(document.createTextNode(' '));
    par.appendChild(texto(valor));
    return par;
}

function popup(nome, ponto) {
    const caixa = document.createElement('div');
    caixa.className = 'nf-gps-popup';
    const titulo = document.createElement('strong');
    titulo.appendChild(texto(nome));
    caixa.appendChild(titulo);
    caixa.appendChild(linha('Ordem:', ponto.ordem));
    caixa.appendChild(linha('Registro:', ponto.tipo));
    if (ponto.hora) {
        caixa.appendChild(linha('Quando:', new Date(ponto.hora).toLocaleString()));
    }
    return caixa;
}

function iconeDePessoa() {
    return L.divIcon({
        className: 'nf-gps-marcador',
        html: '<i class="fa-solid fa-person-walking" aria-hidden="true"></i>',
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
}

export function init() {
    const raiz = document.querySelector('[data-nf-gps]');
    const alvo = document.getElementById('nf-gps-mapa');

    if (!raiz || !alvo) {
        return;
    }

    let equipe = [];

    try {
        equipe = JSON.parse(raiz.dataset.nfGpsDados || '[]');
    } catch {
        equipe = [];
    }

    const centros = equipe.flatMap((pessoa) => [
        ...pessoa.rota.map((ponto) => [ponto.lat, ponto.lng]),
        ...(pessoa.posicao ? [[pessoa.posicao.lat, pessoa.posicao.lng]] : []),
    ]);

    const mapa = L.map(alvo, { scrollWheelZoom: false })
        .setView(centros[0] ?? [-15.78, -47.93], centros.length > 0 ? 12 : 4);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(mapa);

    const alvos = new Map();

    equipe.forEach((pessoa, indice) => {
        const cor = CORES[indice % CORES.length];
        const pontos = pessoa.rota.map((ponto) => [ponto.lat, ponto.lng]);

        if (pontos.length > 1) {
            L.polyline(pontos, { color: cor, weight: 3, opacity: 0.75 }).addTo(mapa);
        }

        pessoa.rota.forEach((ponto) => {
            L.circleMarker([ponto.lat, ponto.lng], {
                radius: 5,
                color: cor,
                weight: 2,
                fillColor: cor,
                fillOpacity: 0.3,
            })
                .bindPopup(popup(pessoa.nome, ponto))
                .addTo(mapa);
        });

        const posicao = pessoa.posicao
            ?? (pessoa.rota.length > 0
                ? { lat: pessoa.rota[pessoa.rota.length - 1].lat, lng: pessoa.rota[pessoa.rota.length - 1].lng }
                : null);

        if (!posicao) {
            return;
        }

        const marcador = L.marker([posicao.lat, posicao.lng], { icon: iconeDePessoa() })
            .bindPopup(popup(pessoa.nome, {
                ordem: 'Posição informada',
                tipo: pessoa.funcao,
                hora: pessoa.atualizado_em,
            }))
            .addTo(mapa);

        alvos.set(String(pessoa.id), { posicao, marcador });
    });

    if (centros.length > 1) {
        mapa.fitBounds(L.latLngBounds(centros).pad(0.2));
    }

    document.querySelectorAll('[data-nf-gps-foco]').forEach((botao) => {
        botao.addEventListener('click', () => {
            const alvoPessoa = alvos.get(botao.dataset.nfGpsFoco);

            if (!alvoPessoa) {
                return;
            }

            mapa.setView([alvoPessoa.posicao.lat, alvoPessoa.posicao.lng], 15);
            alvoPessoa.marcador.openPopup();
        });
    });

    const status = raiz.querySelector('[data-nf-gps-status]');
    const botaoCompartilhar = raiz.querySelector('[data-nf-gps-compartilhar]');

    function avisar(mensagem) {
        if (!status) {
            return;
        }

        status.textContent = mensagem;
        status.hidden = false;
    }

    if (!botaoCompartilhar || !navigator.geolocation) {
        return;
    }

    let observador = null;
    let ultimoEnvio = 0;

    async function enviar(coordenadas) {
        if (Date.now() - ultimoEnvio < INTERVALO_MINIMO_MS) {
            return;
        }

        ultimoEnvio = Date.now();

        try {
            const resposta = await fetch(raiz.dataset.nfGpsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': raiz.dataset.nfGpsToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    latitude: coordenadas.latitude,
                    longitude: coordenadas.longitude,
                }),
            });

            if (!resposta.ok) {
                throw new Error('falha ao registrar');
            }

            avisar('Sua posição foi atualizada no mapa da empresa.');
        } catch {
            avisar('Não foi possível registrar sua posição. Tentaremos de novo na próxima leitura.');
        }
    }

    botaoCompartilhar.addEventListener('click', () => {
        if (observador !== null) {
            navigator.geolocation.clearWatch(observador);
            observador = null;
            botaoCompartilhar.setAttribute('aria-pressed', 'false');
            avisar('Compartilhamento interrompido.');
            return;
        }

        observador = navigator.geolocation.watchPosition(
            (posicao) => enviar(posicao.coords),
            () => avisar('O navegador não conseguiu ler a sua posição.'),
            { enableHighAccuracy: true, maximumAge: 30000, timeout: 15000 },
        );
        botaoCompartilhar.setAttribute('aria-pressed', 'true');
        avisar('Compartilhamento ativo. Sua posição será atualizada no mapa.');
    });
}
