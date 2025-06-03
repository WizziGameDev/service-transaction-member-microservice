# ===== BUILD STAGE =====
FROM composer:2.7 AS build

WORKDIR /app

COPY . .

RUN composer require vladimir-yuldashev/laravel-queue-rabbitmq --no-interaction --ignore-platform-req=ext-sockets \
    && composer install --ignore-platform-req=ext-sockets --prefer-dist --no-dev --optimize-autoloader

# ===== APP STAGE =====
FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    bash \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql intl gd zip sockets


RUN apk add --no-cache nodejs npm

WORKDIR /var/www

COPY --from=build /app /var/www

RUN chown -R www-data:www-data /var/www \
    && chmod -R 755 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
