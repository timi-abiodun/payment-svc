FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    git unzip libsqlite3-dev sqlite3 \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && touch database/database.sqlite \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 10000
CMD php artisan migrate --force \
    && php artisan config:cache \
    && php artisan serve --host=0.0.0.0 --port=10000