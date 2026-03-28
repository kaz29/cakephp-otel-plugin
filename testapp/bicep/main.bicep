targetScope = 'resourceGroup'

@description('リソースのデプロイ先リージョン')
param location string = 'japaneast'

@description('環境名プレフィックス')
param envPrefix string = 'otel-test'

@secure()
@description('PostgreSQL管理者パスワード')
param dbAdminPassword string

@description('コンテナイメージ名 (例: myacr.azurecr.io/app:latest)')
param containerImageName string = ''

// --- Resource Names ---
var logAnalyticsName = '${envPrefix}-law'
var appInsightsName = '${envPrefix}-ai'
var acrName = replace('${envPrefix}acr', '-', '')
var postgresServerName = '${envPrefix}-pg'
var environmentName = '${envPrefix}-cae'
var appName = '${envPrefix}-app'

// --- 1. Log Analytics + Application Insights ---
module log 'modules/log.bicep' = {
  name: 'log'
  params: {
    location: location
    logAnalyticsName: logAnalyticsName
    appInsightsName: appInsightsName
  }
}

// --- 2. ACR / PostgreSQL (並列) ---
module acr 'modules/acr.bicep' = {
  name: 'acr'
  params: {
    location: location
    acrName: acrName
  }
}

module postgres 'modules/postgres.bicep' = {
  name: 'postgres'
  params: {
    location: location
    serverName: postgresServerName
    adminPassword: dbAdminPassword
  }
}

// --- 3. Container Apps Environment ---
module cae 'modules/cae.bicep' = {
  name: 'cae'
  params: {
    location: location
    environmentName: environmentName
    logAnalyticsCustomerId: log.outputs.logAnalyticsCustomerId
    logAnalyticsSharedKey: log.outputs.logAnalyticsSharedKey
  }
}

// --- 4. Container App (イメージ指定時のみデプロイ) ---
module ca 'modules/ca.bicep' = if (!empty(containerImageName)) {
  name: 'ca'
  params: {
    location: location
    appName: appName
    managedEnvironmentId: cae.outputs.environmentId
    acrLoginServer: acr.outputs.acrLoginServer
    containerImage: containerImageName
    dbHost: postgres.outputs.serverFqdn
    dbUser: postgres.outputs.adminUser
    dbPassword: dbAdminPassword
    dbName: postgres.outputs.databaseName
    appInsightsConnectionString: log.outputs.appInsightsConnectionString
  }
}

// --- ACR Pull権限の付与 ---
resource acrResource 'Microsoft.ContainerRegistry/registries@2023-07-01' existing = {
  name: acrName
}

var acrPullRoleId = '7f951dda-4ed3-4680-a7ca-43fe172d538d' // AcrPull

resource acrPullRoleAssignment 'Microsoft.Authorization/roleAssignments@2022-04-01' = if (!empty(containerImageName)) {
  name: guid(acrResource.id, acrPullRoleId, appName)
  scope: acrResource
  properties: {
    #disable-next-line BCP318
    principalId: ca.outputs.appPrincipalId
    roleDefinitionId: subscriptionResourceId('Microsoft.Authorization/roleDefinitions', acrPullRoleId)
    principalType: 'ServicePrincipal'
  }
}

// --- Outputs ---
output acrLoginServer string = acr.outputs.acrLoginServer
output postgresServerFqdn string = postgres.outputs.serverFqdn
output appInsightsConnectionString string = log.outputs.appInsightsConnectionString
output containerAppsEnvironmentDomain string = cae.outputs.defaultDomain
#disable-next-line BCP318
output appFqdn string = !empty(containerImageName) ? ca.outputs.appFqdn : 'N/A'
