@description('リソースのデプロイ先リージョン')
param location string

@description('Container App名')
param appName string

@description('Container Apps Environment ID')
param managedEnvironmentId string

@description('ACRログインサーバー')
param acrLoginServer string

@description('コンテナイメージ名 (例: myacr.azurecr.io/app:latest)')
param containerImage string

@description('PostgreSQL ホスト名')
param dbHost string

@description('PostgreSQL ユーザー名')
param dbUser string

@secure()
@description('PostgreSQL パスワード')
param dbPassword string

@description('PostgreSQL データベース名')
param dbName string

@description('Application Insights 接続文字列')
param appInsightsConnectionString string

resource containerApp 'Microsoft.App/containerApps@2024-08-02-preview' = {
  name: appName
  location: location
  identity: {
    type: 'SystemAssigned'
  }
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
        {
          name: 'db-password'
          value: dbPassword
        }
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
            { name: 'OTEL_EXPORTER_OTLP_ENDPOINT', value: 'https://japaneast-0.in.applicationinsights.azure.com/v2/track' }
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
}

output appFqdn string = containerApp.properties.configuration.ingress.fqdn
output appPrincipalId string = containerApp.identity.principalId
