# Step 2: Docker化

## 概要

ローカル開発環境とAzure Container Appsへのデプロイ用のDockerイメージを構築する。

## 2.1 コンテナ構成

```
┌─────────────────────────────────────┐
│         Nginx (port 80)             │
│         ↕ FastCGI                   │
│     PHP-FPM 8.3 + ext-opentelemetry │
└─────────────────────────────────────┘
```

本番用はNginx + PHP-FPMをマルチプロセスで1コンテナにまとめる構成とする（Container Apps向けにシンプルに）。

## 2.2 Dockerfile (本番用)

```dockerfile
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

RUN chown -R www-data:www-data /var/www/html/tmp /var/www/html/logs

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

## 2.3 Nginx設定

```nginx
# docker/nginx/default.conf
server {
    listen 80;
    server_name _;
    root /var/www/html/webroot;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 2.4 Supervisord設定

```ini
# docker/supervisor/supervisord.conf
[supervisord]
nodaemon=true
logfile=/dev/null
logfile_maxbytes=0

[program:php-fpm]
command=php-fpm --nodaemonize
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

## 2.5 Docker Compose (ローカル開発)

```yaml
# docker-compose.yml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8080:80"
    environment:
      - DATABASE_HOST=postgres
      - DATABASE_PORT=5432
      - DATABASE_USER=app
      - DATABASE_PASSWORD=secret
      - DATABASE_NAME=bbs
      - DATABASE_SSLMODE=disable
      - OTEL_PHP_AUTOLOAD_ENABLED=true
      - OTEL_SERVICE_NAME=cakephp-otel-test-app
      - OTEL_TRACES_EXPORTER=otlp
      - OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
      - OTEL_EXPORTER_OTLP_ENDPOINT=http://jaeger:4318
      - OTEL_LOGS_EXPORTER=otlp
      - OTEL_METRICS_EXPORTER=none
    depends_on:
      postgres:
        condition: service_healthy
      jaeger:
        condition: service_started

  postgres:
    image: postgres:16
    environment:
      POSTGRES_USER: app
      POSTGRES_PASSWORD: secret
      POSTGRES_DB: bbs
    ports:
      - "5432:5432"
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U app -d bbs"]
      interval: 5s
      timeout: 3s
      retries: 5

  jaeger:
    image: jaegertracing/all-in-one:latest
    ports:
      - "16686:16686"  # Jaeger UI
      - "4317:4317"    # OTLP gRPC
      - "4318:4318"    # OTLP HTTP
    environment:
      COLLECTOR_OTLP_ENABLED: "true"

volumes:
  pgdata:
```

## 2.6 ローカル開発手順

```bash
# コンテナ起動
docker compose up -d

# マイグレーション実行
docker compose exec app bin/cake migrations migrate

# ブラウザで確認
open http://localhost:8080

# Jaeger UIでトレース確認
open http://localhost:16686
```
