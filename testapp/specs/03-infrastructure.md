# Step 3: Azure インフラ構築 (Bicep)

## 概要

Azure Container Apps + Azure Database for PostgreSQL Flexible Server の環境をBicepで構築する。
[kaz29/contaner-apps-labo](https://github.com/kaz29/contaner-apps-labo) を参考にするが、Private Endpointは使用しない簡易構成とする。

## 3.1 アーキテクチャ

```
Internet
  │
  ▼
Container Apps Environment (public access: enabled)
  ├── Container App: cakephp-otel-test-app
  │     ├── Nginx + PHP-FPM (ext-opentelemetry)
  │     └── → OTLP → Application Insights
  │
  └── (OTLP Endpoint)
        └── Application Insights ← Log Analytics Workspace

Azure Database for PostgreSQL Flexible Server
  └── Database: bbs
```

- Container Appsは `publicNetworkAccess: Enabled` で直接公開
- Azure Front Doorは不要 (Private Endpoint不使用のため)
- PostgreSQLもpublic accessで接続（検証用途）

## 3.2 Bicepモジュール構成

```
bicep/
├── main.bicep                  # メインテンプレート
├── parameters.bicepparam       # パラメータファイル
└── modules/
    ├── log.bicep               # Log Analytics + Application Insights
    ├── cae.bicep               # Container Apps Environment
    ├── ca.bicep                # Container App
    ├── acr.bicep               # Azure Container Registry
    └── postgres.bicep          # Azure Database for PostgreSQL
```

## 3.3 リソース詳細

### Log Analytics Workspace + Application Insights (`modules/log.bicep`)

contaner-apps-laboと同様の構成。

```bicep
resource logAnalytics 'Microsoft.OperationalInsights/workspaces@2023-09-01' = {
  name: logAnalyticsName
  location: location
  properties: {
    sku: { name: 'PerGB2018' }
    retentionInDays: 30
  }
}

resource appInsights 'Microsoft.Insights/components@2020-02-02' = {
  name: appInsightsName
  location: location
  kind: 'web'
  properties: {
    Application_Type: 'web'
    WorkspaceResourceId: logAnalytics.id
  }
}
```

### Container Apps Environment (`modules/cae.bicep`)

contaner-apps-laboとの違い: `publicNetworkAccess: 'Enabled'`

```bicep
resource managedEnvironment 'Microsoft.App/managedEnvironments@2024-08-02-preview' = {
  name: environmentName
  location: location
  properties: {
    appLogsConfiguration: {
      destination: 'log-analytics'
      logAnalyticsConfiguration: {
        customerId: logAnalyticsCustomerId
        sharedKey: logAnalyticsSharedKey
      }
    }
    publicNetworkAccess: 'Enabled'
  }
}
```

### Container App (`modules/ca.bicep`)

```bicep
resource containerApp 'Microsoft.App/containerApps@2024-08-02-preview' = {
  name: appName
  location: location
  properties: {
    managedEnvironmentId: managedEnvironmentId
    configuration: {
      ingress: {
        external: true
        targetPort: 80
        transport: 'http'
      }
      registries: [
        {
          server: acrLoginServer
          identity: 'system'
        }
      ]
      secrets: [
        { name: 'db-password', value: dbPassword }
      ]
    }
    template: {
      containers: [
        {
          name: 'app'
          image: containerImage
          resources: {
            cpu: json('0.5')
            memory: '1Gi'
          }
          env: [
            { name: 'DATABASE_HOST', value: dbHost }
            { name: 'DATABASE_PORT', value: '5432' }
            { name: 'DATABASE_USER', value: dbUser }
            { name: 'DATABASE_PASSWORD', secretRef: 'db-password' }
            { name: 'DATABASE_NAME', value: dbName }
            { name: 'DATABASE_SSLMODE', value: 'require' }
            { name: 'OTEL_PHP_AUTOLOAD_ENABLED', value: 'true' }
            { name: 'OTEL_SERVICE_NAME', value: 'cakephp-otel-test-app' }
            { name: 'OTEL_TRACES_EXPORTER', value: 'otlp' }
            { name: 'OTEL_EXPORTER_OTLP_PROTOCOL', value: 'http/protobuf' }
            { name: 'OTEL_EXPORTER_OTLP_ENDPOINT', value: appInsightsOtlpEndpoint }
            { name: 'OTEL_EXPORTER_OTLP_HEADERS', value: 'x-ms-client-request-id=${appInsightsConnectionString}' }
            { name: 'OTEL_LOGS_EXPORTER', value: 'otlp' }
            { name: 'OTEL_METRICS_EXPORTER', value: 'none' }
          ]
        }
      ]
      scale: {
        minReplicas: 1
        maxReplicas: 3
      }
    }
  }
  identity: {
    type: 'SystemAssigned'
  }
}
```

### Azure Container Registry (`modules/acr.bicep`)

```bicep
resource acr 'Microsoft.ContainerRegistry/registries@2023-07-01' = {
  name: acrName
  location: location
  sku: { name: 'Basic' }
  properties: {
    adminUserEnabled: false
  }
}
```

Container AppのSystem Assigned Managed IdentityにACRのAcrPull権限を付与する。

### Azure Database for PostgreSQL Flexible Server (`modules/postgres.bicep`)

最小SKUで検証用途に構築する。

```bicep
resource postgresServer 'Microsoft.DBforPostgreSQL/flexibleServers@2023-12-01-preview' = {
  name: serverName
  location: location
  sku: {
    name: 'Standard_B1ms'       // 最小SKU: 1 vCore, 2 GiB RAM
    tier: 'Burstable'
  }
  properties: {
    version: '16'
    administratorLogin: adminUser
    administratorLoginPassword: adminPassword
    storage: {
      storageSizeGB: 32          // 最小ストレージ
    }
    backup: {
      backupRetentionDays: 7
      geoRedundantBackup: 'Disabled'
    }
    highAvailability: {
      mode: 'Disabled'           // 検証用なのでHA不要
    }
    network: {
      publicNetworkAccess: 'Enabled'
    }
  }
}

// Container Apps Environmentからのアクセスを許可するFirewallルール
resource firewallRule 'Microsoft.DBforPostgreSQL/flexibleServers/firewallRules@2023-12-01-preview' = {
  parent: postgresServer
  name: 'AllowAzureServices'
  properties: {
    startIpAddress: '0.0.0.0'
    endIpAddress: '0.0.0.0'
  }
}

// アプリ用データベース
resource database 'Microsoft.DBforPostgreSQL/flexibleServers/databases@2023-12-01-preview' = {
  parent: postgresServer
  name: databaseName
  properties: {
    charset: 'UTF8'
    collation: 'en_US.utf8'
  }
}
```

## 3.4 メインテンプレート (`main.bicep`)

```bicep
targetScope = 'resourceGroup'

@description('リソースのデプロイ先リージョン')
param location string = 'japaneast'

@description('環境名プレフィックス')
param envPrefix string = 'otel-test'

@secure()
@description('PostgreSQL管理者パスワード')
param dbAdminPassword string

@description('コンテナイメージ名')
param containerImageName string = ''

// --- Modules ---

module log 'modules/log.bicep' = { ... }
module acr 'modules/acr.bicep' = { ... }
module postgres 'modules/postgres.bicep' = { ... }
module cae 'modules/cae.bicep' = { ... }
module ca 'modules/ca.bicep' = { ... }
```

デプロイ順序:
1. Log Analytics + Application Insights
2. ACR / PostgreSQL (並列)
3. Container Apps Environment
4. Container App

## 3.5 パラメータ

| パラメータ | 説明 | デフォルト |
|-----------|------|-----------|
| location | リージョン | japaneast |
| envPrefix | リソース名プレフィックス | otel-test |
| dbAdminPassword | PostgreSQL管理者パスワード | (必須) |
| containerImageName | デプロイするコンテナイメージ | (初回は空) |

## 3.6 Application InsightsのOTLP設定

Application InsightsはOTLPプロトコルでのテレメトリ受信をサポートしている。

Container Appの環境変数で以下を設定:
- `OTEL_EXPORTER_OTLP_ENDPOINT`: Application InsightsのOTLPエンドポイント
- `OTEL_EXPORTER_OTLP_HEADERS`: Application Insightsの接続文字列を含むヘッダー

※ Application InsightsのOTLP対応状況に応じて、Azure Monitor OpenTelemetry Distroの利用も検討する。

## 3.7 デプロイコマンド

```bash
# リソースグループ作成
az group create --name rg-otel-test --location japaneast

# インフラデプロイ
az deployment group create \
  --resource-group rg-otel-test \
  --template-file bicep/main.bicep \
  --parameters bicep/parameters.bicepparam
```
