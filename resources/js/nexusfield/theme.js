/*
 * Tema global: branco neutro (claro) e fundo preto (escuro), ambos sem casta de cor.
 *
 * A preferência vai para cookie (o Blade entrega o HTML já com o tema, sem
 * flash) e para localStorage (leitura instantânea no cliente). O atributo
 * `data-bs-theme` inicial é aplicado por um script inline no <head>; aqui só
 * mantemos a chave, a cor da barra do celular, o esquema nativo do <html> e o
 * sistema operacional.
 */
const STORAGE_KEY = 'nexusfield:tema';
const COOKIE_NAME = 'nf_theme';
const COOKIE_DAYS = 365;
const MODES = new Set(['light', 'dark']);
const CLASSE_DA_VIRADA = 'nf-tema-virando';
const JANELA_DA_VIRADA = 260;

let virada = null;
const BROWSER_BAR_COLOR = {
    light: '#f5f6f8',
    dark: '#000000',
};

function readCookie() {
    const match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_NAME + '=([^;]*)'));
    const value = match ? decodeURIComponent(match[1]) : null;

    return MODES.has(value) ? value : null;
}

function writeCookie(mode) {
    const expires = new Date(Date.now() + COOKIE_DAYS * 864e5).toUTCString();
    document.cookie = `${COOKIE_NAME}=${mode}; Expires=${expires}; Path=/; SameSite=Lax`;
}

export function current() {
    return document.documentElement.dataset.bsTheme === 'dark' ? 'dark' : 'light';
}

// O AdminLTE 4 escreve `color-scheme` como estilo inline no <html> quando liga, e
// estilo inline vence a regra do tokens.css. Sem acompanhar aqui, a chave da casa
// troca a cara da página mas deixa barra de rolagem, campo de data e checkbox com
// a tinta do tema anterior até a próxima recarga — que é exatamente o que a
// varredura de navegador pegou no dia 09/10/2026.
function syncNativeScheme(mode) {
    document.documentElement.style.colorScheme = mode;
}

function syncBrowserBar(mode) {
    const meta = document.querySelector('meta[name="theme-color"]');

    if (meta) {
        meta.setAttribute('content', BROWSER_BAR_COLOR[mode]);
    }
}

// A pílula mostra o tema atual pelo CSS, mas o estado também precisa chegar ao
// leitor de tela: aria-pressed="true" significa "estou no modo escuro".
function syncToggleState(mode) {
    document.querySelectorAll('[data-nf-theme-toggle]').forEach((toggle) => {
        toggle.setAttribute('aria-pressed', mode === 'dark' ? 'true' : 'false');
    });
}

/**
 * A cor só desliza quando alguém escolhe trocar: a folha só anima enquanto o
 * <html> veste `nf-tema-virando`, e a classe sai na janela seguinte. Pintar na
 * carga não entra aqui de propósito — o tema já chega certo do <head>.
 */
function animaVirada() {
    const raiz = document.documentElement;

    raiz.classList.add(CLASSE_DA_VIRADA);
    window.clearTimeout(virada);
    virada = window.setTimeout(() => raiz.classList.remove(CLASSE_DA_VIRADA), JANELA_DA_VIRADA);
}

export function apply(mode, { remember = true } = {}) {
    if (!MODES.has(mode)) {
        return;
    }

    const mudou = document.documentElement.dataset.bsTheme !== mode;
    document.documentElement.dataset.bsTheme = mode;
    syncNativeScheme(mode);
    syncBrowserBar(mode);
    syncToggleState(mode);

    if (mudou) {
        animaVirada();
    }

    if (remember) {
        window.localStorage?.setItem(STORAGE_KEY, mode);
        writeCookie(mode);
    }

    document.dispatchEvent(new CustomEvent('nf:tema', { detail: { modo: mode } }));
}

export function toggle() {
    apply(current() === 'dark' ? 'light' : 'dark');
}

export function init() {
    syncNativeScheme(current());
    syncBrowserBar(current());
    syncToggleState(current());

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-nf-theme-toggle]');

        if (trigger) {
            event.preventDefault();
            toggle();
        }
    });

    // Quem nunca escolheu acompanha o sistema; quem escolheu mantém a escolha.
    window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', (event) => {
        if (!window.localStorage?.getItem(STORAGE_KEY) && !readCookie()) {
            apply(event.matches ? 'dark' : 'light', { remember: false });
        }
    });
}
