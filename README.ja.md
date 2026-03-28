# CakePHP OpenTelemetry Plugin

[![Tests](https://github.com/kaz29/cakephp-otel-plugin/actions/workflows/tests.yml/badge.svg)](https://github.com/kaz29/cakephp-otel-plugin/actions/workflows/tests.yml)

[English](README.md)

CakePHP 5 アプリケーションに OpenTelemetry 計装を追加するプラグインです。`ext-opentelemetry` の `zend_observer` フックを利用し、コード変更なしで Controller / Table のスパンを自動生成します。

## 要件

- PHP 8.3+
- CakePHP 5.x
- `ext-opentelemetry` PECL 拡張

## インストール

```bash
composer require kaz29/otel-instrumentation
```

`config/bootstrap.php` または `Application::bootstrap()` でプラグインを読み込みます:

```php
$this->addPlugin('OtelInstrumentation');
```

## 計装対象

| 対象 | スパン名の例 |
|---|---|
| `Controller::invokeAction` | `App\Controller\UsersController::index` |
| `Table::find` | `Users.find(all)` |
| `Table::save` | `Users.save` |
| `Table::delete` | `Users.delete` |

## 環境変数

```bash
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-cakephp-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
```

## OtelErrorLoggingMiddleware

500系例外を OpenTelemetry ログレコードとして送信する PSR-15 ミドルウェアです。ログは現在のスパンに自動で紐づくため、トレースバックエンド（Jaeger / Grafana Tempo など）のトレース画面から関連エラーをそのまま参照できます。

- `HttpException` でステータスコード 500 以上: 送信します
- `HttpException` でステータスコード 400 系 (例: 404): 送信しません
- `HttpException` 以外の例外（予期しないエラー）: 500 として送信します

### 設定

`Application::middleware()` で `ErrorHandlerMiddleware` の**後**（内側）に配置してください:

```php
use OtelInstrumentation\Middleware\OtelErrorLoggingMiddleware;

public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
{
    $middlewareQueue
        ->add(new ErrorHandlerMiddleware())
        ->add(new OtelErrorLoggingMiddleware())
        // ...
    ;
}
```

## TraceAwareLogger

PSR-3 LoggerInterface の Decorator です。ログの `context` に `trace_id` / `span_id` を自動付与します。

```php
$logger = new \OtelInstrumentation\Log\TraceAwareLogger($existingPsr3Logger);
```

## デモアプリケーション (testapp)

`testapp/` ディレクトリにサンプル CakePHP アプリケーションを同梱しています。Posts/Comments の CRUD アプリにプラグインを組み込み済みで、PostgreSQL と Jaeger を Docker Compose で提供します。

### 起動方法

```bash
cd testapp
docker compose up -d
docker compose exec app composer install
docker compose exec app bin/cake migrations migrate
```

- アプリ: http://localhost:8080
- Jaeger UI: http://localhost:16686

アプリにアクセスした後、Jaeger UI を開くとプラグインが生成したトレースを確認できます。

### 仕組み

testapp の `composer.json` は Composer の `path` リポジトリを使い、親ディレクトリのプラグインをシンボリックリンクでインストールします。プラグインのソースを変更すると再インストールなしで即座に反映されます。

## ライセンス

MIT
