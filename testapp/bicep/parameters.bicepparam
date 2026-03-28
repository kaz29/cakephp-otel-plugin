using './main.bicep'

param location = 'japaneast'
param envPrefix = 'otel-test'
param dbAdminPassword = readEnvironmentVariable('DB_ADMIN_PASSWORD', '')
param containerImageName = readEnvironmentVariable('CONTAINER_IMAGE_NAME', '')
