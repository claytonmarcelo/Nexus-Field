# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# NEXUS-FIELD / Vercel Container Runtime
#
# Vercel nao oferece runtime PHP nativo. Esta imagem roda nginx + PHP-FPM em um
# unico container OCI, escutando na porta 80 (ou na porta definida por $PORT).
# O filesystem da imagem e somente leitura; todo estado gravavel vai para /tmp
# atraves de APP_STORAGE_PATH. Sessao vai no cookie, cache em arquivo e a fila
# fica no banco externo. Variaveis definidas na Vercel sobrescrevem estes padroes.
# ---------------------------------------------------------------------------

FROM php:8.4-fpm-alpine AS php-base

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Extensoes exigidas por Laravel, DomPDF e PhpSpreadsheet. As bibliotecas de
# runtime ficam na imagem; apenas os cabecalhos de compilacao sao removidos.
RUN apk add --no-cache \
        curl \
        freetype \
        icu-libs \
        libjpeg-turbo \
        libpq \
        libpng \
        libxml2 \
        libzip \
        oniguruma \
    && apk add --no-cache --virtual .php-build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        jpeg-dev \
        libpng-dev \
        libpq-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        dom \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        xml \
        xmlreader \
        xmlwriter \
        zip \
    && apk del .php-build-deps \
    && cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

# ---------------------------------------------------------------------------
# Dependencias de producao (sem dev, sem scripts que dependam de .env)
# ---------------------------------------------------------------------------
FROM php-base AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --optimize-autoloader

COPY . .
# O `package:discover` do Laravel le TODOS os pacotes de vendor/composer/installed.json,
# inclusive os de require-dev que o Composer ainda lista la. Isso faz o build procurar
# BoostServiceProvider (ferramenta de desenvolvimento) numa imagem --no-dev. Filtra
# pelo proprio `dev-package-names` antes de gerar o manifesto de producao.
RUN rm -f bootstrap/cache/*.php \
    && composer dump-autoload --optimize --no-dev --classmap-authoritative --no-scripts \
    && php -r '$p="vendor/composer/installed.json"; $j=json_decode(file_get_contents($p),true); $dev=$j["dev-package-names"]??[]; $j["packages"]=array_values(array_filter($j["packages"],fn($x)=>!in_array($x["name"],$dev,true))); $j["dev"]=false; file_put_contents($p,json_encode($j,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));' \
    && php artisan package:discover --ansi

# ---------------------------------------------------------------------------
# Assets compilados pelo Vite
# ---------------------------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# ---------------------------------------------------------------------------
# Imagem final
# ---------------------------------------------------------------------------
FROM php-base AS runtime

RUN apk add --no-cache nginx ca-certificates \
    && mkdir -p /run/nginx \
        /tmp/nexusfield/bootstrap/cache \
        /tmp/nexusfield/storage/app/private \
        /tmp/nexusfield/storage/app/public \
        /tmp/nexusfield/storage/framework/cache/data \
        /tmp/nexusfield/storage/framework/sessions \
        /tmp/nexusfield/storage/framework/testing \
        /tmp/nexusfield/storage/framework/views \
        /tmp/nexusfield/storage/logs

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/99-nexusfield.ini /usr/local/etc/php/conf.d/99-nexusfield.ini
COPY docker/entrypoint.sh /usr/local/bin/nexus-entrypoint
RUN chmod 0755 /usr/local/bin/nexus-entrypoint

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/bootstrap/cache ./bootstrap/cache
COPY --from=assets /app/public/build ./public/build

# Padroes seguros. Qualquer variavel definida no projeto Vercel sobrescreve
# estes valores. APP_KEY e APP_URL devem ser definidas no projeto, nunca aqui.
ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_STORAGE_PATH=/tmp/nexusfield/storage \
    APP_CONFIG_CACHE=/tmp/nexusfield/bootstrap/cache/config.php \
    APP_EVENTS_CACHE=/tmp/nexusfield/bootstrap/cache/events.php \
    APP_ROUTES_CACHE=/tmp/nexusfield/bootstrap/cache/routes-v7.php \
    APP_MAINTENANCE_DRIVER=cache \
    CACHE_STORE=file \
    FILESYSTEM_DISK=local \
    LOG_CHANNEL=stderr \
    LOG_STACK=stderr \
    LOG_LEVEL=info \
    QUEUE_CONNECTION=database \
    SESSION_DRIVER=cookie \
    SESSION_SECURE_COOKIE=true \
    PORT=80

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/nexus-entrypoint"]
