@description('リソースのデプロイ先リージョン')
param location string

@description('Container Apps Environment名')
param environmentName string

@description('Log Analytics Workspace カスタマーID')
param logAnalyticsCustomerId string

@secure()
@description('Log Analytics Workspace 共有キー')
param logAnalyticsSharedKey string

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

output environmentId string = managedEnvironment.id
output defaultDomain string = managedEnvironment.properties.defaultDomain
