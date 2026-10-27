FROM php:8.5-fpm-alpine AS base
WORKDIR /app

# Production dependencies only, installed in their own stage so the final image carries no Composer.
FROM base AS vendor
RUN apk add --no-cache unzip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY src ./src
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

FROM base AS app
COPY --from=vendor /app/vendor ./vendor
COPY src ./src
COPY public ./public
COPY bin ./bin
# The SQLite file lives here; a named volume mounted on /data keeps it between restarts.
RUN mkdir /data && chown www-data:www-data /data
USER www-data

FROM base AS dev
RUN apk add --no-cache git unzip $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
