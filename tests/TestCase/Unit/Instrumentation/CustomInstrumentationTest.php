<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Unit\Instrumentation;

use OtelInstrumentation\Instrumentation\CustomInstrumentation;
use OtelInstrumentation\Instrumentation\HookDefinition;
use PHPUnit\Framework\TestCase;

class CustomInstrumentationTest extends TestCase
{
    protected function tearDown(): void
    {
        CustomInstrumentation::reset();
        parent::tearDown();
    }

    public function testLoadFromConfigValidEntries(): void
    {
        CustomInstrumentation::loadFromConfig([
            ['class' => 'App\\Service\\Foo', 'method' => 'bar'],
            [
                'class' => 'App\\Service\\Baz',
                'method' => 'qux',
                'spanName' => 'custom.qux',
                'attributes' => ['key' => 'value'],
            ],
        ]);

        $definitions = $this->getDefinitions();
        $this->assertCount(2, $definitions);
        $this->assertSame('App\\Service\\Foo', $definitions[0]->class);
        $this->assertSame('bar', $definitions[0]->method);
        $this->assertNull($definitions[0]->spanName);
        $this->assertSame('App\\Service\\Baz', $definitions[1]->class);
        $this->assertSame('custom.qux', $definitions[1]->spanName);
        $this->assertSame(['key' => 'value'], $definitions[1]->attributes);
    }

    public function testLoadFromConfigMissingClassThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OtelInstrumentation.hooks[0] must have "class" and "method" keys.');

        CustomInstrumentation::loadFromConfig([
            ['method' => 'bar'],
        ]);
    }

    public function testLoadFromConfigMissingMethodThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('OtelInstrumentation.hooks[0] must have "class" and "method" keys.');

        CustomInstrumentation::loadFromConfig([
            ['class' => 'App\\Service\\Foo'],
        ]);
    }

    public function testDuplicateRegistrationIsIgnored(): void
    {
        CustomInstrumentation::register('App\\Service\\Foo', 'bar');
        CustomInstrumentation::register('App\\Service\\Foo', 'bar', spanName: 'duplicate');

        $definitions = $this->getDefinitions();
        $this->assertCount(1, $definitions);
        $this->assertNull($definitions[0]->spanName);
    }

    public function testDuplicateViaAddIsIgnored(): void
    {
        CustomInstrumentation::register('App\\Service\\Foo', 'bar');
        CustomInstrumentation::add(new HookDefinition(
            class: 'App\\Service\\Foo',
            method: 'bar',
            spanName: 'duplicate',
        ));

        $definitions = $this->getDefinitions();
        $this->assertCount(1, $definitions);
    }

    public function testDuplicateViaLoadFromConfigIsIgnored(): void
    {
        CustomInstrumentation::register('App\\Service\\Foo', 'bar');
        CustomInstrumentation::loadFromConfig([
            ['class' => 'App\\Service\\Foo', 'method' => 'bar'],
            ['class' => 'App\\Service\\Foo', 'method' => 'baz'],
        ]);

        $definitions = $this->getDefinitions();
        $this->assertCount(2, $definitions);
        $this->assertSame('bar', $definitions[0]->method);
        $this->assertSame('baz', $definitions[1]->method);
    }

    public function testSameClassDifferentMethodsAllowed(): void
    {
        CustomInstrumentation::register('App\\Service\\Foo', 'bar');
        CustomInstrumentation::register('App\\Service\\Foo', 'baz');

        $definitions = $this->getDefinitions();
        $this->assertCount(2, $definitions);
    }

    /**
     * @return HookDefinition[]
     */
    private function getDefinitions(): array
    {
        $ref = new \ReflectionClass(CustomInstrumentation::class);
        $prop = $ref->getProperty('definitions');

        return $prop->getValue();
    }
}
