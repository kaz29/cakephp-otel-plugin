# CakePHP OTel Test App

[kaz29/cakephp-otel-plugin](https://github.com/kaz29/cakephp-otel-plugin) を Azure 環境にデプロイし、OpenTelemetry によるトレース・ログ収集を検証するためのテストアプリケーション。

## ローカル開発

```bash
docker compose up -d
```

`http://localhost:8765` でアクセス。

## Azure インフラデプロイ

### 前提

- Azure CLI (`az`) がインストール済みであること
- `az login` でログイン済みであること

### 環境変数の設定

```bash
export RESOURCE_GROUP=rg-otel-test
export DB_ADMIN_PASSWORD='<PostgreSQL管理者パスワード>'
```

### 1. リソースグループ作成

```bash
az group create --name "$RESOURCE_GROUP" --location japaneast
```

### 2. インフラデプロイ (初回)

初回はコンテナイメージがまだ無いため、Container App 以外のリソースを構築します。

```bash
az deployment group create \
  --resource-group "$RESOURCE_GROUP" \
  --template-file bicep/main.bicep \
  --parameters bicep/parameters.bicepparam
```

### 3. コンテナイメージのビルド & プッシュ

```bash
ACR_NAME=$(az acr list --resource-group "$RESOURCE_GROUP" --query '[0].name' -o tsv)

az acr build \
  --registry "$ACR_NAME" \
  --image cakephp-otel-test-app:latest \
  .
```

### 4. Container App のデプロイ

```bash
ACR_LOGIN_SERVER=$(az acr show --name "$ACR_NAME" --query loginServer -o tsv)

CONTAINER_IMAGE_NAME="${ACR_LOGIN_SERVER}/cakephp-otel-test-app:latest" \
az deployment group create \
  --resource-group "$RESOURCE_GROUP" \
  --template-file bicep/main.bicep \
  --parameters bicep/parameters.bicepparam
```

### 5. デプロイ確認

```bash
az deployment group show \
  --resource-group "$RESOURCE_GROUP" \
  --name main \
  --query properties.outputs
```

### リソース削除

```bash
az group delete --name "$RESOURCE_GROUP" --yes --no-wait
```
