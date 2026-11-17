FROM dunglas/frankenphp:1-php8.4-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        pkg-config \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libzip-dev \
        libxml2-dev \
        libsqlite3-dev \
        libonig-dev \
        libpq-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd pdo_pgsql pgsql \
    && install-php-extensions \
        intl \
        bcmath \
        pdo_sqlite \
        sqlite3 \
        xml \
        mbstring \
        zip \
        opcache \
        pdo_mysql \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /app

FROM node:22-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build

FROM base AS dependencies

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./

ENV COMPOSER_MEMORY_LIMIT=-1

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
        database \
    && chmod -R 775 storage bootstrap/cache \
    && export APP_ENV=local \
    && export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')" \
    && export APP_DEBUG=false \
    && export DB_CONNECTION=sqlite \
    && export DB_DATABASE=/app/database/database.sqlite \
    && export CACHE_STORE=array \
    && export SESSION_DRIVER=cookie \
    && export QUEUE_CONNECTION=sync \
    && export LOG_CHANNEL=stderr \
    && composer dump-autoload --no-dev --classmap-authoritative \
    && touch database/database.sqlite \
    && php artisan migrate --force --no-interaction

FROM base AS runtime

COPY --from=dependencies --chown=www-data:www-data /app /app
COPY --chown=www-data:www-data Caddyfile /etc/frankenphp/Caddyfile
COPY --chown=www-data:www-data docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod 755 /usr/local/bin/entrypoint.sh \
    && setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp \
    && chmod -R 775 /app/storage /app/bootstrap/cache \
    && chown -R www-data:www-data /config/caddy /data/caddy /app/storage /app/bootstrap/cache /app/database

ENV PORT=80 \
    APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    CACHE_STORE=array \
    SESSION_DRIVER=cookie \
    QUEUE_CONNECTION=sync \
    VIEW_COMPILED_PATH=/tmp/storage/framework/views

USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
