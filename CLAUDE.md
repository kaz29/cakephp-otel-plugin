# CakePHP OpenTelemetry Plugin

CakePHP 5 用の OpenTelemetry 計装プラグイン。`ext-opentelemetry` の `zend_observer` フックを使い、Controller / ORM 操作のスパンを自動生成する。

## プロジェクト構成

```
src/
├── Plugin.php                          # bootstrap で計装を読み込む
├── Instrumentation/
│   ├── ControllerInstrumentation.php   # Controller::invokeAction フック
│   └── TableInstrumentation.php        # Table::find/save/delete フック
├── Log/
│   └── TraceAwareLogger.php            # PSR-3 Decorator（trace_id/span_id 埋め込み）
└── Util/
    └── DbSystemResolver.php            # ドライバー名 → OTel db.system マッピング

tests/
├── bootstrap.php                       # テスト用 DB 接続 & テーブル作成
└── TestCase/
    ├── Unit/
    │   ├── Log/TraceAwareLoggerTest.php
    │   └── Util/DbSystemResolverTest.php
    └── Integration/
        ├── ControllerInstrumentationTest.php
        └── TableInstrumentationTest.php
```

## 開発環境

- PHP 8.3+ / CakePHP 5.x / `ext-opentelemetry` PECL 拡張が必要
- Docker Compose で PHP 8.4（otel-app）/ PHP 8.3（otel-app-8.3）+ PostgreSQL 環境を提供

## テスト実行

```bash
# Docker 起動
docker compose up -d

# Unit テスト（DB 不要）
docker compose exec otel-app vendor/bin/phpunit --testsuite unit
docker compose exec otel-app-8.3 vendor/bin/phpunit --testsuite unit

# Integration テスト（PostgreSQL 必要）
docker compose exec otel-app vendor/bin/phpunit --testsuite integration

# 全テスト
docker compose exec otel-app vendor/bin/phpunit
```

## CI

GitHub Actions（`.github/workflows/tests.yml`）で PHP 8.3 / 8.4 / 8.5 の matrix テストを実行。PostgreSQL サービスコンテナを使用。

## 計装対象

| フック対象 | スパン名の例 | SpanKind |
|---|---|---|
| `Controller::invokeAction` | `App\Controller\UsersController::index` | SERVER |
| `Table::find` | `Users.find(all)` | CLIENT |
| `Table::save` | `Users.save` | CLIENT |
| `Table::delete` | `Users.delete` | CLIENT |

## 設計上の注意点

- **ログは OTel で送信しない** — TraceAwareLogger で traceId をログに埋め込み、ログ基盤側で突合する方式
- **save() の戻り値チェック** — `save()` は例外を投げず `false` を返すケースがあるため、`$returnValue === false` も STATUS_ERROR として記録する
- **DbSystemResolver** — CakePHP ドライバークラス名を OTel セマンティック規約の `db.system` 値（mysql / postgresql / sqlite / mssql / other_sql）に変換する。取得失敗時は `other_sql` にフォールバック
- **フロントとの分散トレーシング** — OTel PHP SDK はデフォルトで W3C TraceContext プロパゲーターを使用するため、W3C 対応のフロントエンド SDK と組み合わせれば追加設定不要
- **バックエンド非依存** — OTLP でテレメトリを送信するため、Jaeger / Zipkin / Grafana Tempo / Application Insights など OTLP 対応の任意のバックエンドで利用可能

## コーディング規約

- 名前空間: `OtelInstrumentation\`
- テスト名前空間: `OtelInstrumentation\Test\`
- テストでは InMemoryExporter + SimpleSpanProcessor でスパンを検証する
