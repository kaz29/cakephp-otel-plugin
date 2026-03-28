# Step 5: OTel検証手順

## 概要

デプロイ後、OpenTelemetryによるトレース・ログが正しく収集されていることを確認する。

## 5.1 検証マトリクス

| # | 操作 | 期待されるスパン | 確認ポイント |
|---|------|-----------------|-------------|
| 1 | 投稿一覧 (GET /posts) | Controller: `PostsController::index` + DB: `Table::find` | ページネーションクエリのスパン |
| 2 | 投稿作成 (POST /posts/add) | Controller: `PostsController::add` + DB: `Table::save` | INSERT のスパン、エンティティが新規 |
| 3 | 投稿詳細 (GET /posts/view/1) | Controller: `PostsController::view` + DB: `Table::find` (contain) | posts + comments のJOIN/サブクエリ |
| 4 | 投稿編集 (POST /posts/edit/1) | Controller: `PostsController::edit` + DB: `Table::save` | UPDATE のスパン、エンティティが既存 |
| 5 | コメント追加 (POST /comments/add/1) | Controller: `CommentsController::add` + DB: `Table::save` | comments テーブルへのINSERT |
| 6 | コメント削除 (POST /comments/delete/1) | Controller: `CommentsController::delete` + DB: `Table::delete` | comments テーブルのDELETE |
| 7 | 投稿削除 (POST /posts/delete/1) | Controller: `PostsController::delete` + DB: `Table::delete` | カスケード削除 (posts + comments) |
| 8 | 404エラー (GET /posts/view/9999) | Controller スパン + エラーステータス | `STATUS_ERROR`、例外記録 |

## 5.2 ローカル検証 (Jaeger)

### 手順

1. `docker compose up -d` でローカル環境起動
2. `http://localhost:8080` でアプリにアクセス
3. 検証マトリクスの各操作を実行
4. `http://localhost:16686` でJaeger UIを開く
5. Service: `cakephp-otel-test-app` を選択してトレースを確認

### 確認事項

- [ ] 各操作でトレースが生成されている
- [ ] Controller スパンが `KIND_SERVER` で記録されている
- [ ] DB スパンが `KIND_CLIENT` で記録されている
- [ ] Controller → DB の親子関係が正しい
- [ ] スパン属性 (http.method, db.system=postgresql 等) が正しい
- [ ] エラー時に `STATUS_ERROR` とexception情報が記録されている

## 5.3 Azure環境検証 (Application Insights)

### 手順

1. Azure Portal → Application Insights → Transaction search
2. アプリにアクセスして各操作を実行
3. 数分待ってからApplication Insightsでテレメトリを確認

### Application Insightsでの確認

#### トレース確認
```kusto
// Application Insights > Logs (KQL)
// 直近のリクエストトレース
requests
| where cloud_RoleName == 'cakephp-otel-test-app'
| order by timestamp desc
| take 50
```

#### DB依存関係の確認
```kusto
// DB呼び出しの確認
dependencies
| where cloud_RoleName == 'cakephp-otel-test-app'
| where type == 'postgresql' or type == 'SQL'
| order by timestamp desc
| take 50
```

#### エラーの確認
```kusto
// 例外の確認
exceptions
| where cloud_RoleName == 'cakephp-otel-test-app'
| order by timestamp desc
| take 20
```

#### トレースIDによるE2E追跡
```kusto
// 特定のトレースIDで全スパンを確認
union requests, dependencies, exceptions, traces
| where operation_Id == '<trace-id>'
| order by timestamp asc
```

### 確認事項

- [ ] Application Insightsにテレメトリが到達している
- [ ] requests テーブルにControllerスパンが記録されている
- [ ] dependencies テーブルにDBスパンが記録されている
- [ ] E2Eトレース (リクエスト → DB) が1つのトレースIDで紐づいている
- [ ] ログに trace_id / span_id が含まれている (TraceAwareLogger)
- [ ] エラー発生時にexceptionsテーブルに記録されている

## 5.4 トラブルシューティング

| 症状 | 確認ポイント |
|------|-------------|
| テレメトリが送信されない | `OTEL_PHP_AUTOLOAD_ENABLED=true` が設定されているか |
| スパンが生成されない | `ext-opentelemetry` がインストールされているか (`php -m \| grep opentelemetry`) |
| Application Insightsに表示されない | OTLPエンドポイントとConnection Stringが正しいか |
| DBスパンがない | プラグインが正しくロードされているか (`$this->addPlugin('OtelInstrumentation')`) |
| PostgreSQLの `db.system` が正しくない | `DbSystemResolver` がPostgresドライバを認識しているか |

## 5.5 成功基準

以下をすべて満たした場合、検証成功とする:

1. **Controller計装**: 全CRUD操作でControllerスパンが生成される
2. **DB計装**: find/save/delete の各操作でDBスパンが生成される
3. **トレース伝播**: 1リクエスト内のController→DBが同一トレースIDで紐づく
4. **エラーハンドリング**: 例外発生時にスパンにエラー情報が記録される
5. **ログ連携**: TraceAwareLoggerによりログにtrace_id/span_idが付与される
6. **Azure統合**: Application Insightsでトレース・依存関係・例外が確認できる
