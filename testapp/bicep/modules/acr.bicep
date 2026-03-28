@description('リソースのデプロイ先リージョン')
param location string

@description('Azure Container Registry名')
param acrName string

resource acr 'Microsoft.ContainerRegistry/registries@2023-07-01' = {
  name: acrName
  location: location
  sku: {
    name: 'Basic'
  }
  properties: {
    adminUserEnabled: false
  }
}

output acrId string = acr.id
output acrLoginServer string = acr.properties.loginServer
output acrName string = acr.name
