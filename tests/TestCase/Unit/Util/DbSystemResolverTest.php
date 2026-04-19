<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Unit\Util;

use OtelInstrumentation\Util\DbSystemResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DbSystemResolverTest extends TestCase
{
    #[DataProvider('driverClassProvider')]
    public function testResolveFromDriverClass(string $driverClass, string $expected): void
    {
        $this->assertSame($expected, DbSystemResolver::resolveFromDriverClass($driverClass));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function driverClassProvider(): array
    {
        return [
            'CakePHP Mysql driver' => ['Cake\Database\Driver\Mysql', 'mysql'],
            'CakePHP Postgres driver' => ['Cake\Database\Driver\Postgres', 'postgresql'],
            'CakePHP Sqlite driver' => ['Cake\Database\Driver\Sqlite', 'sqlite'],
            'CakePHP Sqlserver driver' => ['Cake\Database\Driver\Sqlserver', 'mssql'],
            'Custom Mysql driver' => ['App\Database\Driver\CustomMysql', 'mysql'],
            'Custom Postgres driver' => ['App\Database\Driver\CustomPostgres', 'postgresql'],
            'Unknown driver' => ['App\Database\Driver\SomeOtherDriver', 'other_sql'],
            'Empty string' => ['', 'other_sql'],
        ];
    }

    public function testResolveFromTableFallsBackOnError(): void
    {
        $table = $this->createMock(\Cake\ORM\Table::class);
        $table->method('getConnection')->willThrowException(new \RuntimeException('No connection'));

        $this->assertSame('other_sql', DbSystemResolver::resolveFromTable($table));
    }
}
