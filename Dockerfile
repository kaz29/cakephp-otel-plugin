FROM kaz29/php-apache:8.4.1

RUN pecl install opentelemetry && docker-php-ext-enable opentelemetry

WORKDIR /srv/app
