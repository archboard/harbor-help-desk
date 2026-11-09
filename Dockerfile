# syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.5 AS base
WORKDIR /app

RUN install-php-extensions pdo_pgsql intl redis pcntl opcache zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini "$PHP_INI_DIR/conf.d/99-app.ini"

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
# Booting the app at build time has no Reverb keys, so don't resolve the broadcaster
RUN BROADCAST_CONNECTION=null composer dump-autoload --optimize --no-dev \
    && BROADCAST_CONNECTION=null php artisan package:discover

# The Vite wayfinder plugin runs `php artisan wayfinder:generate`, so the asset
# build needs PHP and Node side by side
FROM base AS assets
COPY --from=node:24-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && npm ci \
    && BROADCAST_CONNECTION=null npm run build

FROM base AS app
ARG HARBOR_VERSION=dev
ENV HARBOR_VERSION=$HARBOR_VERSION
COPY --from=assets /app/public/build public/build
RUN chown -R www-data:www-data storage bootstrap/cache /data/caddy /config/caddy

USER www-data
EXPOSE 8000
ENTRYPOINT ["docker/entrypoint.sh"]
CMD ["frankenphp", "php-server", "--root", "public", "--listen", ":8000"]
