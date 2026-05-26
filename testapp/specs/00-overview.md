# CakePHP OTel Test App - 全体概要

## 目的

[kaz29/cakephp-otel-plugin](https://github.com/kaz29/cakephp-otel-plugin) を実際のAzure環境にデプロイし、OpenTelemetryによるトレース・ログの収集を検証するためのテストアプリケーション。

## アプリケーション概要: ミニ掲示板 (Mini BBS)

シンプルな掲示板アプリケーションを構築する。以下の理由からこの構成を選択:

- **Controller計装の検証**: 複数のコントローラアクション (一覧/詳細/作成/編集/削除) でスパンが正しく生成されることを確認
- **Database計装の検証**: `Table::find()`, `Table::save()`, `Table::delete()` の各オペレーションでDBスパンが生成されることを確認
- **リレーション跡の検証**: 投稿→コメントの親子関係で、複数テーブルにまたがるクエリのトレースを確認
- **エラーハンドリングの検証**: OtelErrorLoggingMiddleware による例外キャプチャを確認
- **TraceAwareLoggerの検証**: ログに trace_id/span_id が付与されることを確認

### 機能一覧

| 機能 | 説明 | 検証対象 |
|------|------|----------|
| 投稿一覧 | ページネーション付きの投稿一覧表示 | find() + ページネーション |
| 投稿詳細 | 投稿とコメント一覧の表示 | find() with contain |
| 投稿作成 | タイトル・本文を入力して投稿 | save() (新規) |
| 投稿編集 | 既存の投稿を編集 | save() (更新) |
| 投稿削除 | 投稿を削除 (コメントも連動) | delete() |
| コメント追加 | 投稿にコメントを追加 | save() (新規) |
| コメント削除 | コメントを削除 | delete() |

### データモデル

```
posts
├── id (SERIAL, PK)
├── title (VARCHAR(255), NOT NULL)
├── body (TEXT, NOT NULL)
├── created (TIMESTAMP)
└── modified (TIMESTAMP)

comments
├── id (SERIAL, PK)
├── post_id (INTEGER, FK → posts.id)
├── author (VARCHAR(100), NOT NULL)
├── body (TEXT, NOT NULL)
├── created (TIMESTAMP)
└── modified (TIMESTAMP)
```

## 技術スタック

| レイヤー | 技術 |
|---------|------|
| 言語 | PHP 8.3 |
| フレームワーク | CakePHP 5.x |
| OTelプラグイン | kaz29/cakephp-otel-plugin |
| データベース | Azure Database for PostgreSQL Flexible Server |
| コンテナ | Docker (PHP-FPM + Nginx) |
| インフラ | Azure Container Apps |
| IaC | Bicep |
| 監視 | Azure Monitor / Application Insights (OTLP経由) |

## ステップ構成

| Step | 内容 | ドキュメント |
|------|------|-------------|
| 1 | CakePHP アプリケーション構築 | [01-application.md](./01-application.md) |
| 2 | Docker化 | [02-docker.md](./02-docker.md) |
| 3 | Azure インフラ構築 (Bicep) | [03-infrastructure.md](./03-infrastructure.md) |
| 4 | CI/CD & デプロイ | [04-deployment.md](./04-deployment.md) |
| 5 | OTel検証手順 | [05-verification.md](./05-verification.md) |
