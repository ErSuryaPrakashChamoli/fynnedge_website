# syntax=docker/dockerfile:1

##
## 1) Build frontend assets (Vite + Tailwind)
##
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY vite.config.js ./
COPY resources/ resources/
COPY public/ public/
RUN npm run build


##
## 2) Install PHP dependencies
##
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader


##
## 3) Runtime image: php-fpm + nginx + supervisor
##
FROM php:8.3-fpm-alpine AS app

RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        libpng \
        libzip \
        libxml2 \
        icu-libs \
        oniguruma \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        curl-dev \
        libpng-dev \
        libzip-dev \
        libxml2-dev \
        icu-dev \
        oniguruma-dev \
        linux-headers \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        curl \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/*

COPY docker/php/php.ini "$PHP_INI_DIR/conf.d/99-app.ini"

WORKDIR /var/www/html

# Application code (vendor and built assets are excluded via .dockerignore
# and copied in explicitly from the stages above). www-data is the user
# already shipped by this image and used by the default php-fpm pool.
COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=frontend --chown=www-data:www-data /app/public/build ./public/build

RUN mkdir -p storage/framework/{cache,sessions,testing,views} storage/framework/cache/data \
    && php artisan package:discover --no-interaction \
    && php artisan filament:upgrade \
    && chown -R www-data:www-data storage bootstrap/cache public \
    && chmod -R 775 storage bootstrap/cache

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
