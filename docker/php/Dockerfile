FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache bash curl git unzip postgresql17-client icu-dev libjpeg-turbo-dev libpng-dev libzip-dev freetype-dev \
        postgresql-dev oniguruma-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) bcmath exif gd intl opcache pcntl pdo pdo_pgsql pgsql posix zip \
    && pecl install redis && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Development image (default target): code is bind-mounted by docker-compose.yml.
FROM base AS dev
ARG UID=1000
ARG GID=1000
# Match the host user so files written in the bind mount stay owned by the developer.
RUN apk add --no-cache shadow && usermod -u ${UID} www-data && groupmod -o -g ${GID} www-data \
    && chown -R www-data:www-data /var/www/html
USER www-data
EXPOSE 9000
CMD ["php-fpm"]
