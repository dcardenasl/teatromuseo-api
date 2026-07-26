# Multi-stage Dockerfile for CodeIgniter 4 API

# Stage 1: Composer dependencies
FROM composer:2 AS composer-build

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .

RUN composer dump-autoload --optimize --no-dev

# Stage 2: Production image
FROM php:8.2-apache

LABEL maintainer="CodeIgniter 4 API Starter"
LABEL description="Production-ready CI4 API with JWT authentication"

# Install system deps, PHP extensions, and enable Apache modules in one layer.
# `apt-get upgrade` first so transitive OS packages pick up any patch released
# after this base image tag was last rebuilt, instead of trusting whatever was
# baked in.
RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends \
        git \
        curl \
        zip \
        unzip \
        openssl \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        intl \
        mysqli \
        pdo \
        pdo_mysql \
        zip \
        gd \
        opcache \
    && a2enmod rewrite headers expires deflate \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/*

# Copy custom PHP configuration
COPY docker/php/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Configure Apache
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application files from composer stage
COPY --from=composer-build /app /var/www/html

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/writable

# Create necessary directories
RUN mkdir -p writable/cache writable/logs writable/session writable/uploads writable/debugbar \
    && chown -R www-data:www-data writable

# First-run bootstrap (idempotent): ensures .env, generates secrets,
# runs migrations + seeder. See docker/entrypoint.sh for details.
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/ci4-entrypoint

# Health check — uses /ping (lightweight, no DB dependency)
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ping || exit 1

# Expose port 80
EXPOSE 80

# Apache runs as www-data internally; entrypoint must run as root to write
# .env and execute migrations, then hand off to apache (which drops privileges).
ENTRYPOINT ["ci4-entrypoint"]
CMD ["apache2-foreground"]
