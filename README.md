# CakePHP OpenTelemetry Plugin

[![Tests](https://github.com/kaz29/cakephp-otel-plugin/actions/workflows/tests.yml/badge.svg)](https://github.com/kaz29/cakephp-otel-plugin/actions/workflows/tests.yml)

[日本語版🇯🇵](README.ja.md)

A CakePHP 5 plugin that adds OpenTelemetry instrumentation to your application. It uses `ext-opentelemetry`'s `zend_observer` hooks to automatically generate spans for Controller and Table operations without any code changes.

## Requirements

- PHP 8.3+
- CakePHP 5.x
- `ext-opentelemetry` PECL extension

## Installation

```bash
composer require kaz29/cakephp-otel-plugin
```

Load the plugin in `config/bootstrap.php` or `Application::bootstrap()`:

```php
$this->addPlugin('OtelInstrumentation');
```

## Instrumented Targets

| Target | Span name example |
|---|---|
| `Controller::invokeAction` | `App\Controller\UsersController::index` |
| `Table::find` | `Users.find(all)` |
| `Table::save` | `Users.save` |
| `Table::delete` | `Users.delete` |

## Custom Instrumentation

You can instrument any class method by registering custom hooks. The plugin uses the same `\OpenTelemetry\Instrumentation\hook()` mechanism as the built-in Controller/Table instrumentation.

### Via Configure (simple)

```php
// config/bootstrap.php or config/app_local.php
use Cake\Core\Configure;
use OpenTelemetry\API\Trace\SpanKind;

Configure::write('OtelInstrumentation.hooks', [
    // Minimal — span name auto-generated as "App\Service\PaymentService::charge"
    ['class' => \App\Service\PaymentService::class, 'method' => 'charge'],

    // With options
    [
        'class' => \App\Service\PaymentService::class,
        'method' => 'refund',
        'spanName' => 'payment.refund',
        'kind' => SpanKind::KIND_CLIENT,
        'attributes' => ['payment.provider' => 'stripe'],
    ],
]);
```

### Via static registration (advanced)

Use `CustomInstrumentation::register()` when you need dynamic attributes via callback:

```php
// In Application::bootstrap(), before $this->addPlugin('OtelInstrumentation')
use OtelInstrumentation\Instrumentation\CustomInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;

CustomInstrumentation::register(
    \App\Service\PaymentService::class,
    'charge',
    spanName: 'payment.charge',
    kind: SpanKind::KIND_CLIENT,
    attributes: ['payment.provider' => 'stripe'],
    attributeCallback: fn($instance, $params, $class, $function) => [
        'payment.amount' => $params[0] ?? null,
    ],
);
```

### Options

| Option | Type | Default | Description |
|---|---|---|---|
| `class` | `string` | (required) | Fully qualified class name |
| `method` | `string` | (required) | Method name to hook |
| `spanName` | `string\|null` | `FQCN::method` | Custom span name |
| `kind` | `int` | `KIND_INTERNAL` | SpanKind constant |
| `attributes` | `array` | `[]` | Static span attributes |
| `attributeCallback` | `Closure\|null` | `null` | `fn($instance, $params, $class, $function): array` |

## Environment Variables

```bash
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-cakephp-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
```

## OtelErrorLoggingMiddleware

A PSR-15 middleware that catches 500-level exceptions and emits them as OpenTelemetry log records. The log is automatically associated with the current span, so you can view related errors directly in your trace backend (Jaeger, Grafana Tempo, etc.).

- `HttpException` with status code >= 500: logged
- `HttpException` with status code < 500 (e.g. 404): not logged
- Non-`HttpException` (unexpected errors): logged as 500

### Setup

Add it **after** `ErrorHandlerMiddleware` in your `Application::middleware()`:

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

A PSR-3 LoggerInterface decorator that automatically injects `trace_id` / `span_id` into log `context`.

```php
$logger = new \OtelInstrumentation\Log\TraceAwareLogger($existingPsr3Logger);
```

## License

MIT
