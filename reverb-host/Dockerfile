FROM php:8.3-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    && docker-php-ext-install -j"$(nproc)" zip pcntl sockets \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
# Skip Composer scripts until the app tree exists (post-autoload-dump runs `php artisan`).
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist

COPY . .
# apps.json is bind-mounted at runtime; image build only needs a valid file for `artisan package:discover`.
RUN cp apps.json.example apps.json
RUN composer dump-autoload --optimize --no-dev --no-interaction --classmap-authoritative

RUN mkdir -p bootstrap/cache storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8080

CMD ["php", "artisan", "reverb:start"]
