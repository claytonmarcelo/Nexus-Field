# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# NEXUS-FIELD / Vercel Container Runtime
#
# Vercel nao oferece runtime PHP nativo. Esta imagem roda nginx + PHP-FPM em um
# unico container OCI, escutando na porta 80 (ou na porta definida por $PORT).
# O filesystem da imagem e somente leitura; todo estado gravavel vai para /tmp
# atraves de APP_STORAGE_PATH. Sessao, cache e fila ficam no banco externo.
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
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
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

RUN apk add --no-cache nginx \
    && mkdir -p /run/nginx \
        /tmp/nexusfield/storage/app/private \
        /tmp/nexusfield/storage/app/public \
        /tmp/nexusfield/storage/framework/cache/data \
        /tmp/nexusfield/storage/framework/sessions \
        /tmp/nexusfield/storage/framework/testing \
        /tmp/nexusfield/storage/framework/views \
        /tmp/nexusfield/storage/logs

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
    APP_MAINTENANCE_DRIVER=cache \
    CACHE_STORE=database \
    FILESYSTEM_DISK=s3 \
    LOG_CHANNEL=stderr \
    LOG_STACK=stderr \
    LOG_LEVEL=info \
    QUEUE_CONNECTION=database \
    SESSION_DRIVER=database \
    SESSION_SECURE_COOKIE=true \
    PORT=80

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/nexus-entrypoint"]
