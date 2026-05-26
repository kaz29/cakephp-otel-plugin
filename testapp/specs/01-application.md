# Step 1: CakePHP アプリケーション構築

## 概要

CakePHP 5.x ベースのミニ掲示板アプリケーションを構築する。

## 1.1 プロジェクト初期化

```bash
composer create-project --prefer-dist cakephp/app:~5.0 .
```

### 追加パッケージ

```bash
composer require kaz29/otel-instrumentation
composer require open-telemetry/exporter-otlp
composer require open-telemetry/transport-grpc
```

## 1.2 データベース設定

`config/app_local.php` でPostgreSQLに接続する設定を行う。

```php
'Datasources' => [
    'default' => [
        'driver' => \Cake\Database\Driver\Postgres::class,
        'host' => env('DATABASE_HOST', 'localhost'),
        'port' => env('DATABASE_PORT', '5432'),
        'username' => env('DATABASE_USER', 'app'),
        'password' => env('DATABASE_PASSWORD', ''),
        'database' => env('DATABASE_NAME', 'bbs'),
        'encoding' => 'utf8',
        'sslmode' => env('DATABASE_SSLMODE', 'prefer'),
    ],
],
```

環境変数でDBの接続先を切り替え可能にする。

## 1.3 マイグレーション

### posts テーブル

```php
// config/Migrations/YYYYMMDDHHMMSS_CreatePosts.php
public function change(): void
{
    $table = $this->table('posts');
    $table->addColumn('title', 'string', ['limit' => 255, 'null' => false])
          ->addColumn('body', 'text', ['null' => false])
          ->addColumn('created', 'datetime')
          ->addColumn('modified', 'datetime')
          ->create();
}
```

### comments テーブル

```php
// config/Migrations/YYYYMMDDHHMMSS_CreateComments.php
public function change(): void
{
    $table = $this->table('comments');
    $table->addColumn('post_id', 'integer', ['null' => false])
          ->addColumn('author', 'string', ['limit' => 100, 'null' => false])
          ->addColumn('body', 'text', ['null' => false])
          ->addColumn('created', 'datetime')
          ->addColumn('modified', 'datetime')
          ->addForeignKey('post_id', 'posts', 'id', [
              'delete' => 'CASCADE',
              'update' => 'NO_ACTION',
          ])
          ->create();
}
```

## 1.4 Model

### PostsTable

```php
// src/Model/Table/PostsTable.php
class PostsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('posts');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->hasMany('Comments', [
            'foreignKey' => 'post_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->scalar('title')->maxLength('title', 255)->notEmptyString('title');
        $validator->scalar('body')->notEmptyString('body');
        return $validator;
    }
}
```

### CommentsTable

```php
// src/Model/Table/CommentsTable.php
class CommentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('comments');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->belongsTo('Posts', [
            'foreignKey' => 'post_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->scalar('author')->maxLength('author', 100)->notEmptyString('author');
        $validator->scalar('body')->notEmptyString('body');
        return $validator;
    }
}
```

## 1.5 Controller

### PostsController

```php
// src/Controller/PostsController.php
class PostsController extends AppController
{
    public function index()
    {
        $posts = $this->paginate($this->Posts, [
            'order' => ['Posts.created' => 'DESC'],
            'limit' => 10,
        ]);
        $this->set(compact('posts'));
    }

    public function view($id = null)
    {
        $post = $this->Posts->get($id, contain: ['Comments']);
        $this->set(compact('post'));
    }

    public function add()
    {
        $post = $this->Posts->newEmptyEntity();
        if ($this->request->is('post')) {
            $post = $this->Posts->patchEntity($post, $this->request->getData());
            if ($this->Posts->save($post)) {
                $this->Flash->success(__('投稿を保存しました。'));
                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('投稿の保存に失敗しました。'));
        }
        $this->set(compact('post'));
    }

    public function edit($id = null)
    {
        $post = $this->Posts->get($id);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $post = $this->Posts->patchEntity($post, $this->request->getData());
            if ($this->Posts->save($post)) {
                $this->Flash->success(__('投稿を更新しました。'));
                return $this->redirect(['action' => 'view', $id]);
            }
            $this->Flash->error(__('投稿の更新に失敗しました。'));
        }
        $this->set(compact('post'));
    }

    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $post = $this->Posts->get($id);
        if ($this->Posts->delete($post)) {
            $this->Flash->success(__('投稿を削除しました。'));
        } else {
            $this->Flash->error(__('投稿の削除に失敗しました。'));
        }
        return $this->redirect(['action' => 'index']);
    }
}
```

### CommentsController

```php
// src/Controller/CommentsController.php
class CommentsController extends AppController
{
    public function add($postId = null)
    {
        $comment = $this->Comments->newEmptyEntity();
        if ($this->request->is('post')) {
            $comment = $this->Comments->patchEntity($comment, $this->request->getData());
            $comment->post_id = (int)$postId;
            if ($this->Comments->save($comment)) {
                $this->Flash->success(__('コメントを追加しました。'));
            } else {
                $this->Flash->error(__('コメントの追加に失敗しました。'));
            }
        }
        return $this->redirect(['controller' => 'Posts', 'action' => 'view', $postId]);
    }

    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $comment = $this->Comments->get($id);
        $postId = $comment->post_id;
        if ($this->Comments->delete($comment)) {
            $this->Flash->success(__('コメントを削除しました。'));
        } else {
            $this->Flash->error(__('コメントの削除に失敗しました。'));
        }
        return $this->redirect(['controller' => 'Posts', 'action' => 'view', $postId]);
    }
}
```

## 1.6 View

CakePHPのデフォルトテンプレートエンジンを使用し、シンプルなHTML + CSSで構築する。
Bakeコマンドでテンプレートを生成し、必要に応じて調整する。

### 主要テンプレート

- `templates/Posts/index.php` - 投稿一覧
- `templates/Posts/view.php` - 投稿詳細 + コメント一覧 + コメントフォーム
- `templates/Posts/add.php` - 投稿作成フォーム
- `templates/Posts/edit.php` - 投稿編集フォーム

## 1.7 OTelプラグイン設定

### プラグイン有効化

```php
// src/Application.php の bootstrap() 内
$this->addPlugin('OtelInstrumentation');
```

### ミドルウェア追加

```php
// src/Application.php の middleware() 内
->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))
->add(new OtelErrorLoggingMiddleware())  // ErrorHandlerMiddleware の直後
```

### TraceAwareLogger設定

```php
// config/app.php の Log 設定で TraceAwareLogger を利用
```

### 環境変数

```env
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=cakephp-otel-test-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
OTEL_LOGS_EXPORTER=otlp
OTEL_METRICS_EXPORTER=none
```

Azure環境では `OTEL_EXPORTER_OTLP_ENDPOINT` をApplication InsightsのOTLPエンドポイントに変更する。

## 1.8 ローカル開発環境

ローカル開発時はDocker Compose (Step 2で定義) を使い、Jaeger等でトレースを確認できるようにする。
