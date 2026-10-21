FROM php:8.5-fpm-alpine AS base
WORKDIR /app

FROM base AS app
COPY public ./public
USER www-data

FROM base AS dev
RUN apk add --no-cache git unzip $PHPIZE_DEPS \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer