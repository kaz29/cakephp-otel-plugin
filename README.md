# CakePHP OpenTelemetry Plugin

[![Tests](https://github.com/kaz29/cakephp-otel-plugin/actions/workflows/tests.yml/badge.svg)](https://github.com/kaz29/cakephp-otel-plugin/actions/workflows/tests.yml)

CakePHP 5 アプリケーションに OpenTelemetry 計装を追加するプラグイン。`ext-opentelemetry` の `zend_observer` フックを利用し、コード変更なしで Controller / Table のスパンを自動生成する。

## 要件

- PHP 8.3+
- CakePHP 5.x
- `ext-opentelemetry` PECL 拡張

## インストール

```bash
composer require kaz29/otel-instrumentation
```

`config/bootstrap.php` または `Application::bootstrap()` でプラグインを読み込む:

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

## TraceAwareLogger

PSR-3 LoggerInterface の Decorator。ログの `context` に `trace_id` / `span_id` を自動付与する。

```php
$logger = new \OtelInstrumentation\Log\TraceAwareLogger($existingPsr3Logger);
```

## ライセンス

MIT
