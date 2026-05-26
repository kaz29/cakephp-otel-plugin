# ---- Build stage ----
FROM php:8.3-fpm AS build

RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libicu-dev \
    && docker-php-ext-install intl pdo_pgsql opcache

# OpenTelemetry extension
RUN pecl install opentelemetry && docker-php-ext-enable opentelemetry

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

# ---- Runtime stage ----
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    nginx libpq-dev libicu-dev supervisor \
    && docker-php-ext-install intl pdo_pgsql opcache \
    && pecl install opentelemetry && docker-php-ext-enable opentelemetry \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=build /app /var/www/html
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php/php-production.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/php/www-overrides.conf /usr/local/etc/php-fpm.d/zz-overrides.conf

RUN chown -R www-data:www-data /var/www/html/tmp /var/www/html/logs

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
