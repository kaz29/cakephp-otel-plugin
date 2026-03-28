# OtelInstrumentation Plugin 機能追加仕様

既存プラグインへの追加実装内容をまとめる。

---

## 追加する機能

1. **500系例外を OTel ログとして送信するミドルウェア**
2. **PHPUnit テスト**
3. **ローカル開発環境での OTel 無効化**

---

## 1. OtelErrorLoggingMiddleware

### 概要

500系例外が発生したとき、OTel ログとして送信するミドルウェア。

ログは発生時のスパンに **自動で紐づく**（`Span::getCurrent()` が生きている状態で emit するため、SDK が traceId / spanId を自動付与する）。バックエンド（Jaeger / Grafana Tempo / Application Insights など）のトレース画面からそのまま関連ログを参照できる。

バリデーションエラー（400系）は対象外。サーバーエラー（500系）と予期しない例外のみを送信することでノイズを抑える。

### 追加ファイル

```
src/Middleware/OtelErrorLoggingMiddleware.php
```

### 判定ロジック

| 例外の種類 | 送信するか |
|---|---|
| `HttpException` でコードが 500 以上 | ✅ 送信 |
| `HttpException` でコードが 400 系 | ❌ 送信しない |
| `HttpException` 以外（予期しない例外） | ✅ 送信（500扱い） |

### emit する属性

| 属性 | 内容 |
|---|---|
| `exception.type` | 例外クラス名 |
| `exception.message` | 例外メッセージ |
| `exception.stacktrace` | スタックトレース |
| severity | `ERROR` |

### アプリ側での登録

`src/Application.php` の `middleware()` で `ErrorHandlerMiddleware` の**内側**に配置すること。外側に置くと例外をキャッチできない。

```php
use OtelInstrumentation\Middleware\OtelErrorLoggingMiddleware;

public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
{
    $middlewareQueue
        ->add(new ErrorHandlerMiddleware())
        ->add(new OtelErrorLoggingMiddleware()) // ← ErrorHandler の内側
        // ...
    ;
}
```

---

## 2. PHPUnit テスト

### 概要

`InMemoryExporter` を使ってテスト中に生成されたスパンをキャプチャし、属性・スパン名・ステータスを PHPUnit で検証する。

### 追加・変更ファイル

```
tests/TestCase/OtelTestTrait.php
tests/TestCase/Integration/Middleware/OtelErrorLoggingMiddlewareTest.php
tests/TestCase/Integration/ControllerInstrumentationTest.php  (OtelTestTrait を使うよう変更)
tests/TestCase/Integration/TableInstrumentationTest.php       (OtelTestTrait を使うよう変更)
```

### OtelTestTrait

各テストクラスで `use OtelTestTrait` して使う共通ヘルパー。

提供するメソッド：

| メソッド | 用途 |
|---|---|
| `setUpOtel()` | `InMemoryExporter` を使った `TracerProvider` をセットアップ |
| `getSpans()` | キャプチャされたスパンをすべて返す |
| `getSpansByName(string $name)` | スパン名で絞り込んで返す |
| `getFirstSpan()` | 最初のスパンを返す |
| `getSpanAttribute($span, $key)` | スパンの属性値を取得する |
| `resetOtel()` | テスト間で OTel グローバル状態をリセットする |
| `getLogRecords()` | キャプチャされたログレコードをすべて返す |

`setUp()` で `setUpOtel()`、`tearDown()` で `resetOtel()` を呼ぶこと。

### テストスイートの使い分け

`phpunit.xml` に2つのスイートを定義する。

| スイート | 対象ディレクトリ | ext-opentelemetry |
|---|---|---|
| `unit` | `tests/TestCase/Unit/` | 不要 |
| `integration` | `tests/TestCase/Integration/` | 必要 |

`DbSystemResolverTest` は純粋なロジックテストのため `ext-opentelemetry` 不要。CI では常に動かせる。

フック系のテスト（`ControllerInstrumentationTest` など）は `ext-opentelemetry` がない環境で `markTestSkipped` により自動スキップする。

```bash
# 拡張なし環境（CI など）
./vendor/bin/phpunit --testsuite unit

# 拡張あり環境（ローカルなど）
./vendor/bin/phpunit --testsuite integration
# または全テスト
./vendor/bin/phpunit
```

### composer.json への追加

```json
"require-dev": {
    "phpunit/phpunit": "^10.0 || ^11.0"
},
"autoload-dev": {
    "psr-4": {
        "OtelInstrumentation\\Test\\": "tests/"
    }
}
```

---

## 3. ローカル開発環境での OTel 無効化

### 方法

`.env` に以下を追加するだけで SDK ごと無効化される。プラグイン側の改修は不要。

```bash
OTEL_SDK_DISABLED=true
```

### PHPUnit 実行時に無効化したい場合

`phpunit.xml` のコメントアウトを外す。

```xml
<php>
    <env name="OTEL_SDK_DISABLED" value="true"/>
</php>
```

---

## ログへの traceId 埋め込み（参考）

OTel でログを全量送信する代わりに、既存の CakePHP ログに traceId を埋め込む運用方針。バックエンドのトレースとログ基盤のログを traceId で突き合わせて調査する。

スパンが存在しないリクエスト（ヘルスチェックなど）では `getTraceId()` が `000...000` を返すため、判定して出し分けること。

```php
use OpenTelemetry\API\Trace\Span;

$context = Span::getCurrent()->getContext();
$traceId = $context->getTraceId();
$spanId  = $context->getSpanId();

// ゼロ値は出力しない
if ($context->isValid()) {
    // ログに trace_id / span_id を付与
}
```

CakePHP のログフォーマッターに仕込むのが最適な場所。
