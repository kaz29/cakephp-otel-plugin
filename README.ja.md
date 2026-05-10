# CakePHP OpenTelemetry Plugin

[![Tests](https://github.com/kaz29/cakephp-otel-plugin/actions/workflows/tests.yml/badge.svg)](https://github.com/kaz29/cakephp-otel-plugin/actions/workflows/tests.yml)

[English](README.md)

CakePHP 5 アプリケーションに OpenTelemetry instrumentation を追加するプラグインです。`ext-opentelemetry` の `zend_observer` フックを利用し、コード変更なしで Controller / Table のスパンを自動生成します。

## 要件

- PHP 8.3+
- CakePHP 5.x
- `ext-opentelemetry` PECL 拡張

## インストール

```bash
composer require kaz29/cakephp-otel-plugin
```

`config/bootstrap.php` または `Application::bootstrap()` でプラグインを読み込む:

```php
$this->addPlugin('OtelInstrumentation');
```

## Instrumentation 対象

| 対象 | スパン名の例 |
|---|---|
| `Controller::invokeAction` | `App\Controller\UsersController::index` |
| `Table::find` | `Users.find(all)` |
| `Table::save` | `Users.save` |
| `Table::delete` | `Users.delete` |

## Instrumentation の除外

ロードバランサーやオーケストレーターから秒単位で叩かれる死活監視エンドポイントなど、価値の低いスパンを大量に生成するアクションは、計装対象から外したくなることがあります。Controller/Action 単位で除外を宣言できます:

```php
// config/bootstrap.php または config/app_local.php
use Cake\Core\Configure;

Configure::write('OtelInstrumentation.exclude', [
    // HealthController の全アクションを除外（health/readiness/liveness 系）
    ['controller' => \App\Controller\HealthController::class, 'action' => '*'],

    // 特定の action だけを除外（他の action は通常通り計装される）
    ['controller' => \App\Controller\PostsController::class, 'action' => 'ping'],
]);
```

マッチングは**完全一致**です。`controller` は完全修飾クラス名、`action` はアクション名そのもの。例外として `'action' => '*'` のみワイルドカードとして扱い、その Controller の全アクションにマッチします。`'*'` を `controller` 側に書くことはできません。

除外対象アクションの実行中は、**配下で呼ばれた `Table::find/save/delete` のスパンや、カスタム Instrumentation のスパンも連動して抑制されます**。1つのルールでサブツリー全体を一括で除外できる設計です。アクションを抜けると、次のリクエストからは通常通り計装が再開されます。

この設定は HTTP リクエストの `Controller::invokeAction` 経由のみ影響します。CLI コマンドはそもそも計装対象外です。

## カスタム Instrumentation

任意のクラス・メソッドにフックを登録してスパンを自動生成できます。内部では組み込みの Controller / Table 計装と同じ `\OpenTelemetry\Instrumentation\hook()` を使用しています。

> **注意:** `Controller::invokeAction`、`Table::find`、`Table::save`、`Table::delete` はプラグインが自動計装済みです。これらをカスタムフックとして登録するとスパンが重複します。

### Configure で登録（シンプル）

```php
// config/bootstrap.php または config/app_local.php
use Cake\Core\Configure;
use OpenTelemetry\API\Trace\SpanKind;

Configure::write('OtelInstrumentation.hooks', [
    // 最小構成 — スパン名は "App\Service\PaymentService::charge" が自動生成
    ['class' => \App\Service\PaymentService::class, 'method' => 'charge'],

    // オプション付き
    [
        'class' => \App\Service\PaymentService::class,
        'method' => 'refund',
        'spanName' => 'payment.refund',
        'kind' => SpanKind::KIND_CLIENT,
        'attributes' => ['payment.provider' => 'stripe'],
    ],
]);
```

### 静的メソッドで登録（上級）

動的な属性コールバックが必要な場合は `CustomInstrumentation::register()` を使います:

```php
// Application::bootstrap() 内、$this->addPlugin('OtelInstrumentation') の前に記述
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

### オプション一覧

| オプション | 型 | デフォルト | 説明 |
|---|---|---|---|
| `class` | `string` | (必須) | 対象クラスの完全修飾名 |
| `method` | `string` | (必須) | フック対象のメソッド名 |
| `spanName` | `string\|null` | `FQCN::method` | スパン名のオーバーライド |
| `kind` | `int` | `KIND_INTERNAL` | SpanKind 定数 |
| `attributes` | `array` | `[]` | 静的なスパン属性 |
| `attributeCallback` | `Closure\|null` | `null` | `fn($instance, $params, $class, $function): array` — このオプションは `register()` で使用してください |

## 環境変数

```bash
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-cakephp-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
```

## OtelErrorLoggingMiddleware

500系例外を OpenTelemetry ログレコードとして送信する PSR-15 ミドルウェア。ログは現在のスパンに自動で紐づくため、トレースバックエンド（Jaeger / Grafana Tempo など）のトレース画面から関連エラーをそのまま参照できる。

- `HttpException` でステータスコード 500 以上: 送信
- `HttpException` でステータスコード 400 系 (例: 404): 送信しない
- `HttpException` 以外の例外（予期しないエラー）: 500 として送信

### 設定

`Application::middleware()` で `ErrorHandlerMiddleware` の**後**（内側）に配置する:

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

PSR-3 LoggerInterface の Decorator。ログの `context` に `trace_id` / `span_id` を自動付与する。

```php
$logger = new \OtelInstrumentation\Log\TraceAwareLogger($existingPsr3Logger);
```

## ライセンス

MIT
