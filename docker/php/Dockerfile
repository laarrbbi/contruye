FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip zip curl \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libzip-dev libicu-dev libonig-dev default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath gd zip intl opcache pcntl exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node, for the template engine run as a process (`node financials/cli.js`,
# the Vercel preview's transport, and its test). Its packages are installed
# by the financials service into the shared curso/ folder.
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node

RUN echo "upload_max_filesize=10M\npost_max_size=12M\nmemory_limit=512M" \
    > /usr/local/etc/php/conf.d/construye.ini

WORKDIR /var/www/api
