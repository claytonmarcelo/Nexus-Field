#!/bin/sh
# Entrypoint do container NEXUS-FIELD na Vercel.
# Falha cedo e com mensagem clara quando a configuracao minima esta ausente:
# melhor quebrar o deploy do que servir login/cadastro/recuperacao quebrados.

set -eu

PORT="${PORT:-80}"

# Em container o /run pode nao existir ou ser somente leitura. O PID file do
# FPM vai para /tmp, que e o unico diretorio gravavel garantido pela imagem.
PHP_FPM_PID=/tmp/nexusfield/php-fpm.pid
NGINX_PID=/tmp/nexusfield/nginx.pid

# O container pode receber uma porta dinamica da plataforma.
sed -i "s|^listen 80 default_server;|listen ${PORT} default_server;|" /etc/nginx/conf.d/default.conf

# O filesystem da imagem e somente leitura. Todo caminho gravavel do Laravel
# aponta para /tmp por meio de APP_STORAGE_PATH (definido no Dockerfile).
mkdir -p \
    /tmp/nexusfield/storage/app/private \
    /tmp/nexusfield/storage/app/public \
    /tmp/nexusfield/storage/framework/cache/data \
    /tmp/nexusfield/storage/framework/sessions \
    /tmp/nexusfield/storage/framework/testing \
    /tmp/nexusfield/storage/framework/views \
    /tmp/nexusfield/storage/logs
chown -R www-data:www-data /tmp/nexusfield

if [ -z "${APP_KEY:-}" ]; then
    echo "ERRO: APP_KEY nao definida. Defina a variavel no projeto Vercel e reimplemente." >&2
    exit 1
fi

if [ -z "${APP_URL:-}" ]; then
    echo "ERRO: APP_URL nao definida. Use a URL publica https do projeto." >&2
    exit 1
fi

# Migracoes de producao sob demanda: defina RUN_MIGRATIONS=true, reimplemente
# e remova a variavel depois que o deploy terminar.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Aplicando migracoes de producao..." >&2
    php artisan migrate --force --no-interaction
fi

# -g do FPM e o caminho do arquivo de PID, nao uma diretiva "pid = ...;".
php-fpm -D -g "${PHP_FPM_PID}"

# Evita responder antes de o FPM estar aceitando conexoes.
tentativas=0
until php -r 'exit(@fsockopen("127.0.0.1", 9000, $errno, $errstr, 1) ? 0 : 1);'; do
    tentativas=$((tentativas + 1))
    if [ "$tentativas" -ge 25 ]; then
        echo "ERRO: PHP-FPM nao iniciou em 5 segundos." >&2
        exit 1
    fi
    sleep 0.2
done

encerrar() {
    if [ -f "$NGINX_PID" ]; then
        kill "$(cat "$NGINX_PID")" >/dev/null 2>&1 || true
    fi
    if [ -f "$PHP_FPM_PID" ]; then
        kill "$(cat "$PHP_FPM_PID")" >/dev/null 2>&1 || true
    fi
}

trap encerrar TERM INT

nginx -g "pid ${NGINX_PID}; daemon off;"
