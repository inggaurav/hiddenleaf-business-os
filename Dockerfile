FROM node:24-bookworm-slim AS frontend

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY packages ./packages
COPY public ./public
COPY tsconfig.json vite.config.js ./
RUN npm run build

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git libonig-dev libpng-dev libpq-dev libxml2-dev libzip-dev unzip \
    && docker-php-ext-install bcmath gd mbstring pcntl pdo_pgsql xml zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --prefer-dist --no-interaction --optimize-autoloader
COPY --from=frontend /app/public/build ./public/build

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
