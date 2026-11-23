/*
 * Sessão e entrada: nada de informação velha voltando pelo botão Voltar.
 *
 * O middleware `no-store` cobre todas as respostas web, mas alguns navegadores
 * ainda restauram o DOM do bfcache. `pageshow` com `persisted === true` é o sinal
 * de que a página voltou desse cache: na área autenticada recarregamos para o
 * servidor decidir se a sessão ainda vale; na entrada limpamos e‑mail, senha e
 * “Lembrar de mim”. Uma resposta nova do servidor — inclusive o `old('email')`
 * de uma tentativa falha — nunca passa por esse caminho.
 *
 * Nada é gravado aqui: nem e‑mail, nem senha, nem token em localStorage,
 * sessionStorage ou cookie. A preferência de tema continua sendo a única
 * informação que a casa guarda no navegador.
 */

function limparEntrada(form) {
    form.querySelectorAll('input[name="email"], input[name="password"]').forEach((campo) => {
        campo.value = '';
    });

    const lembrar = form.querySelector('input[name="remember"]');

    if (lembrar) {
        lembrar.checked = false;
    }
}

export function init() {
    const entrada = document.querySelector('form[data-nf-login]');
    const autenticada = document.body?.dataset?.nfSessao === 'autenticada';

    if (!entrada && !autenticada) {
        return;
    }

    window.addEventListener('pageshow', (event) => {
        if (event.persisted !== true) {
            return;
        }

        if (entrada) {
            limparEntrada(entrada);
            return;
        }

        window.location.reload();
    });
}
