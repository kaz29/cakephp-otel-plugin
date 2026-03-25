<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;

// CakePHP basic configuration for testing
Configure::write('debug', true);

// Database connection for integration tests
$dbHost = getenv('DB_HOST') ?: 'otel-database';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('DB_NAME') ?: 'webapp';
$dbUser = getenv('DB_USER') ?: 'webapp';
$dbPassword = getenv('DB_PASSWORD') ?: 'Passw0rd';

ConnectionManager::setConfig('default', [
    'className' => \Cake\Database\Connection::class,
    'driver' => \Cake\Database\Driver\Postgres::class,
    'host' => $dbHost,
    'port' => $dbPort,
    'database' => $dbName,
    'username' => $dbUser,
    'password' => $dbPassword,
]);

// Create test table for integration tests
try {
    $connection = ConnectionManager::get('default');
    $connection->execute('
        CREATE TABLE IF NOT EXISTS otel_test_articles (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            body TEXT,
            created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            modified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ');
} catch (\Throwable $e) {
    // DB not available — skip schema setup (unit tests don't need it)
}
