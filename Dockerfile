# Dockerfile dla produkcji - multi-stage build
FROM php:8.4-fpm-alpine AS base

# Instalacja zależności systemowych
RUN apk add --no-cache \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    $PHPIZE_DEPS \
    linux-headers

# Instalacja rozszerzeń PHP
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    intl \
    opcache \
    zip

RUN apk add --no-cache \
    rabbitmq-c-dev \
    $PHPIZE_DEPS \
    && pecl install amqp redis \
    && docker-php-ext-enable amqp redis \
    && apk del $PHPIZE_DEPS

# Konfiguracja OPcache dla produkcji
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=0'; \
} > /usr/local/etc/php/conf.d/opcache-prod.ini

# Instalacja Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# === Builder stage - instalacja dependencies ===
FROM base AS builder

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

# === Final stage - produkcyjny obraz ===
FROM base AS production

# Kopiowanie aplikacji z buildera
COPY --from=builder /app /app

# Tworzenie katalogu var z odpowiednimi uprawnieniami
RUN mkdir -p var/cache var/log && \
    chown -R www-data:www-data var

# Użytkownik www-data dla bezpieczeństwa
USER www-data

EXPOSE 9000

CMD ["php-fpm"]

# === Development stage ===
FROM base AS development

RUN apk add --no-cache \
    postgresql-dev \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    $PHPIZE_DEPS \
    linux-headers

# Instalacja rozszerzeń PHP
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    intl \
    opcache \
    zip

RUN apk add --no-cache \
    rabbitmq-c-dev \
    $PHPIZE_DEPS \
    && pecl install amqp redis \
    && docker-php-ext-enable amqp redis \
    && apk del $PHPIZE_DEPS

# Konfiguracja OPcache dla produkcji
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=256'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=20000'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.save_comments=1'; \
    echo 'opcache.fast_shutdown=0'; \
} > /usr/local/etc/php/conf.d/opcache-prod.ini

# Instalacja Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

CMD ["php-fpm"]
