#!/bin/sh
# Entrypoint do container NEXUS-FIELD na Vercel.
# Falha cedo e com mensagem clara quando a configuracao minima esta ausente:
# melhor quebrar o deploy do que servir login/cadastro/recuperacao quebrados.

set -eu

PORT="${PORT:-80}"

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

php-fpm -D -g 'pid = /run/php-fpm.pid;'

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
    nginx -s quit >/dev/null 2>&1 || true
    if [ -f /run/php-fpm.pid ]; then
        kill "$(cat /run/php-fpm.pid)" >/dev/null 2>&1 || true
    fi
}

trap encerrar TERM INT

nginx -g 'daemon off;'
