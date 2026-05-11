<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Unit\Instrumentation;

use OtelInstrumentation\Instrumentation\ExclusionRegistry;
use PHPUnit\Framework\TestCase;

class ExclusionRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ExclusionRegistry::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ExclusionRegistry::reset();
    }

    public function testRegisterValidEntriesAccepted(): void
    {
        ExclusionRegistry::register([
            ['controller' => 'App\\Controller\\HealthController', 'action' => '*'],
            ['controller' => 'App\\Controller\\PostsController', 'action' => 'ping'],
        ]);

        $this->assertTrue(ExclusionRegistry::isExcluded('App\\Controller\\HealthController', 'index'));
        $this->assertTrue(ExclusionRegistry::isExcluded('App\\Controller\\PostsController', 'ping'));
    }

    public function testRegisterRejectsMissingControllerKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OtelInstrumentation.exclude[0]');

        ExclusionRegistry::register([
            ['action' => 'index'],
        ]);
    }

    public function testRegisterRejectsMissingActionKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OtelInstrumentation.exclude[0]');

        ExclusionRegistry::register([
            ['controller' => 'App\\Controller\\HealthController'],
        ]);
    }

    public function testRegisterRejectsWildcardController(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"*" is not allowed');

        ExclusionRegistry::register([
            ['controller' => '*', 'action' => 'index'],
        ]);
    }

    public function testRegisterRejectsEmptyAction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('action must be a non-empty string');

        ExclusionRegistry::register([
            ['controller' => 'App\\Controller\\HealthController', 'action' => ''],
        ]);
    }

    public function testIsExcludedExactMatch(): void
    {
        ExclusionRegistry::register([
            ['controller' => 'App\\Controller\\PostsController', 'action' => 'ping'],
        ]);

        $this->assertTrue(ExclusionRegistry::isExcluded('App\\Controller\\PostsController', 'ping'));
        $this->assertFalse(ExclusionRegistry::isExcluded('App\\Controller\\PostsController', 'index'));
    }

    public function testIsExcludedWildcardAction(): void
    {
        ExclusionRegistry::register([
            ['controller' => 'App\\Controller\\HealthController', 'action' => '*'],
        ]);

        $this->assertTrue(ExclusionRegistry::isExcluded('App\\Controller\\HealthController', 'index'));
        $this->assertTrue(ExclusionRegistry::isExcluded('App\\Controller\\HealthController', 'check'));
        $this->assertFalse(ExclusionRegistry::isExcluded('App\\Controller\\OtherController', 'index'));
    }

    public function testIsExcludedReturnsFalseForUnregistered(): void
    {
        $this->assertFalse(ExclusionRegistry::isExcluded('App\\Controller\\AnyController', 'any'));
    }

    public function testEnterIncrementsDepthOnlyWhenExcluded(): void
    {
        ExclusionRegistry::register([
            ['controller' => 'App\\Controller\\HealthController', 'action' => '*'],
        ]);

        $this->assertFalse(ExclusionRegistry::isCurrentlyExcluded());

        $entered = ExclusionRegistry::enter('App\\Controller\\OtherController', 'index');
        $this->assertFalse($entered);
        $this->assertFalse(ExclusionRegistry::isCurrentlyExcluded());

        $entered = ExclusionRegistry::enter('App\\Controller\\HealthController', 'index');
        $this->assertTrue($entered);
        $this->assertTrue(ExclusionRegistry::isCurrentlyExcluded());
    }

    public function testLeaveDecrementsDepth(): void
    {
        ExclusionRegistry::register([
            ['controller' => 'App\\Controller\\HealthController', 'action' => '*'],
        ]);

        ExclusionRegistry::enter('App\\Controller\\HealthController', 'index');
        $this->assertTrue(ExclusionRegistry::isCurrentlyExcluded());

        ExclusionRegistry::leave();
        $this->assertFalse(ExclusionRegistry::isCurrentlyExcluded());
    }

    public function testLeaveIsSafeWhenDepthZero(): void
    {
        ExclusionRegistry::leave();
        ExclusionRegistry::leave();
        $this->assertFalse(ExclusionRegistry::isCurrentlyExcluded());
    }

    public function testRegisterMergesMultipleActionsForSameController(): void
    {
        ExclusionRegistry::register([
            ['controller' => 'App\\Controller\\PostsController', 'action' => 'ping'],
            ['controller' => 'App\\Controller\\PostsController', 'action' => 'health'],
        ]);

        $this->assertTrue(ExclusionRegistry::isExcluded('App\\Controller\\PostsController', 'ping'));
        $this->assertTrue(ExclusionRegistry::isExcluded('App\\Controller\\PostsController', 'health'));
        $this->assertFalse(ExclusionRegistry::isExcluded('App\\Controller\\PostsController', 'index'));
    }

    public function testResetClearsState(): void
    {
        ExclusionRegistry::register([
            ['controller' => 'App\\Controller\\HealthController', 'action' => '*'],
        ]);
        ExclusionRegistry::enter('App\\Controller\\HealthController', 'index');

        ExclusionRegistry::reset();

        $this->assertFalse(ExclusionRegistry::isCurrentlyExcluded());
        $this->assertFalse(ExclusionRegistry::isExcluded('App\\Controller\\HealthController', 'index'));
    }
}
