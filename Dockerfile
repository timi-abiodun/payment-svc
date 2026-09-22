FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip libsqlite3-dev sqlite3 \
    libzip-dev libonig-dev libxml2-dev \
    && docker-php-ext-install pdo pdo_sqlite mbstring xml zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --no-interaction \
    && touch database/database.sqlite \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 10000
CMD php artisan migrate --force \
    && php artisan config:cache \
    && php artisan serve --host=0.0.0.0 --port=10000