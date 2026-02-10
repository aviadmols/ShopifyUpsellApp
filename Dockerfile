# Laravel on Railway – PHP 8.2 with pdo_mysql
FROM php:8.2-cli

# Install system deps + PHP extensions (intl required by Filament)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    --no-install-recommends && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize

# Start script: clear config cache, then serve (Railway sets PORT at runtime)
COPY docker-start.sh /docker-start.sh
RUN chmod +x /docker-start.sh
EXPOSE 8000
CMD ["/docker-start.sh"]
