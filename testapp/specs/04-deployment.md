# Step 4: CI/CD & デプロイ

## 概要

GitHub Actionsを使ったCI/CDパイプラインを構築し、Azure Container Appsへデプロイする。

## 4.1 デプロイフロー

```
Push to main
  │
  ▼
GitHub Actions
  ├── 1. Build Docker image
  ├── 2. Push to ACR
  ├── 3. Update Container App
  └── 4. Run migrations
```

## 4.2 GitHub Actions Workflow

```yaml
# .github/workflows/deploy.yml
name: Deploy to Azure Container Apps

on:
  push:
    branches: [main]
  workflow_dispatch:

env:
  ACR_NAME: acroteltest
  RESOURCE_GROUP: rg-otel-test
  CONTAINER_APP_NAME: ca-otel-test
  IMAGE_NAME: cakephp-otel-test-app

jobs:
  build-and-deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Login to Azure
        uses: azure/login@v2
        with:
          creds: ${{ secrets.AZURE_CREDENTIALS }}

      - name: Login to ACR
        run: az acr login --name ${{ env.ACR_NAME }}

      - name: Build and push image
        run: |
          IMAGE_TAG=${{ env.ACR_NAME }}.azurecr.io/${{ env.IMAGE_NAME }}:${{ github.sha }}
          docker build -t $IMAGE_TAG .
          docker push $IMAGE_TAG

      - name: Deploy to Container App
        run: |
          IMAGE_TAG=${{ env.ACR_NAME }}.azurecr.io/${{ env.IMAGE_NAME }}:${{ github.sha }}
          az containerapp update \
            --name ${{ env.CONTAINER_APP_NAME }} \
            --resource-group ${{ env.RESOURCE_GROUP }} \
            --image $IMAGE_TAG

      - name: Run migrations
        run: |
          az containerapp exec \
            --name ${{ env.CONTAINER_APP_NAME }} \
            --resource-group ${{ env.RESOURCE_GROUP }} \
            --command "bin/cake migrations migrate --no-interaction"
```

## 4.3 必要なGitHub Secrets

| Secret名 | 内容 |
|-----------|------|
| AZURE_CREDENTIALS | Azure Service Principalの認証情報 (JSON) |

### Service Principal作成

```bash
az ad sp create-for-rbac \
  --name "sp-otel-test-deploy" \
  --role contributor \
  --scopes /subscriptions/{subscription-id}/resourceGroups/rg-otel-test \
  --sdk-auth
```

出力されたJSONをGitHub SecretsのAZURE_CREDENTIALSに設定する。

## 4.4 手動デプロイ手順

GitHub Actionsを使わず手動でデプロイする場合:

```bash
# ACRログイン
az acr login --name acroteltest

# ビルド & プッシュ
IMAGE_TAG=acroteltest.azurecr.io/cakephp-otel-test-app:latest
docker build -t $IMAGE_TAG .
docker push $IMAGE_TAG

# Container App更新
az containerapp update \
  --name ca-otel-test \
  --resource-group rg-otel-test \
  --image $IMAGE_TAG

# マイグレーション実行
az containerapp exec \
  --name ca-otel-test \
  --resource-group rg-otel-test \
  --command "bin/cake migrations migrate --no-interaction"
```

## 4.5 初回デプロイ

初回はインフラ構築とアプリデプロイを順次実行する:

1. **インフラ構築** (Step 3): Bicepでリソース作成
2. **ACR権限設定**: Container AppのManaged IdentityにACRのAcrPull権限付与
3. **イメージビルド & プッシュ**: ACRにDockerイメージをプッシュ
4. **Container App更新**: イメージを指定してContainer Appを更新
5. **マイグレーション**: DBスキーマ作成
