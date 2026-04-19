<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Unit\Instrumentation;

use OpenTelemetry\API\Trace\SpanKind;
use OtelInstrumentation\Instrumentation\HookDefinition;
use PHPUnit\Framework\TestCase;

class HookDefinitionTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $def = new HookDefinition(
            class: 'App\\Service\\PaymentService',
            method: 'charge',
        );

        $this->assertSame('App\\Service\\PaymentService', $def->class);
        $this->assertSame('charge', $def->method);
        $this->assertNull($def->spanName);
        $this->assertSame(SpanKind::KIND_INTERNAL, $def->kind);
        $this->assertSame([], $def->attributes);
        $this->assertNull($def->attributeCallback);
    }

    public function testCustomValues(): void
    {
        $callback = fn ($instance, $params, $class, $function) => ['key' => 'value'];

        $def = new HookDefinition(
            class: 'App\\Service\\PaymentService',
            method: 'charge',
            spanName: 'payment.charge',
            kind: SpanKind::KIND_CLIENT,
            attributes: ['provider' => 'stripe'],
            attributeCallback: $callback,
        );

        $this->assertSame('payment.charge', $def->spanName);
        $this->assertSame(SpanKind::KIND_CLIENT, $def->kind);
        $this->assertSame(['provider' => 'stripe'], $def->attributes);
        $this->assertSame($callback, $def->attributeCallback);
    }

    public function testInvalidSpanKindThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SpanKind value: 999');

        new HookDefinition(
            class: 'App\\Service\\PaymentService',
            method: 'charge',
            kind: 999,
        );
    }
}
