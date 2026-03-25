<?php
declare(strict_types=1);

namespace OtelInstrumentation\Util;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Table;

class DbSystemResolver
{
    private const FALLBACK = 'other_sql';

    /**
     * Resolve db.system value from a Table object via its connection.
     *
     * @return 'mysql'|'postgresql'|'sqlite'|'mssql'|'other_sql'
     */
    public static function resolveFromTable(Table $table): string
    {
        try {
            $connection = $table->getConnection();
            $driver = $connection->getDriver();
            $driverClass = get_class($driver);

            return self::resolveFromDriverClass($driverClass);
        } catch (\Throwable) {
            return self::FALLBACK;
        }
    }

    /**
     * Resolve db.system value from a driver class name.
     *
     * @return 'mysql'|'postgresql'|'sqlite'|'mssql'|'other_sql'
     * @see https://opentelemetry.io/docs/specs/semconv/database/database-spans/#notes-and-well-known-identifiers-for-dbsystem
     */
    public static function resolveFromDriverClass(string $driverClass): string
    {
        return match (true) {
            str_contains($driverClass, 'Mysql') => 'mysql',
            str_contains($driverClass, 'Postgres') => 'postgresql',
            str_contains($driverClass, 'Sqlite') => 'sqlite',
            str_contains($driverClass, 'Sqlserver') => 'mssql',
            default => self::FALLBACK,
        };
    }
}
