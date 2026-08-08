FROM php:8.4-fpm-alpine

# ---------------------------------------------------------
# Dependencias del sistema
# ---------------------------------------------------------
RUN apk add --no-cache \
    git \
    curl \
    bash \
    libpng-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    postgresql-dev \
    zip \
    unzip \
    $PHPIZE_DEPS

# ---------------------------------------------------------
# Extensiones PHP requeridas por la arquitectura
# pdo_pgsql, pcntl (Horizon/Reverb), bcmath, intl, opcache
# ---------------------------------------------------------
RUN docker-php-ext-install \
    pdo_pgsql \
    pgsql \
    bcmath \
    gd \
    intl \
    zip \
    pcntl \
    opcache

# ---------------------------------------------------------
# Extensión Redis
# ---------------------------------------------------------
RUN pecl install redis && docker-php-ext-enable redis

# ---------------------------------------------------------
# Configuración de opcache para desarrollo
# ---------------------------------------------------------
RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=4000'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'opcache.fast_shutdown=1'; \
        echo 'opcache.enable_cli=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# ---------------------------------------------------------
# Composer
# ---------------------------------------------------------
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 9000
CMD ["php-fpm"]
