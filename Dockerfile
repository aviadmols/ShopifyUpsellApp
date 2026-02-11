# Laravel on Railway – PHP 8.2 with pdo_mysql
FROM php:8.2-cli

# Install system deps + PHP extensions (intl required by Filament)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    --no-install-recommends && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip \
    && docker-php-ext-configure intl \
    && docker-php-ext-install intl

# Composer (install directly to avoid Docker Hub auth 500 errors)
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

WORKDIR /app

# Dependencies
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

# Ensure Laravel storage + bootstrap/cache exist and are writable (no .env in image)
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache storage/logs bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

RUN composer dump-autoload --optimize

# Start script: clear config cache, then serve (Railway sets PORT at runtime)
COPY docker-start.sh /docker-start.sh
RUN chmod +x /docker-start.sh
EXPOSE 8000
# ENTRYPOINT ensures this always runs (overrides any platform default)
ENTRYPOINT ["/docker-start.sh"]
CMD []
