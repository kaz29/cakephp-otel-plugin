<?php
declare(strict_types=1);

namespace OtelInstrumentation\Instrumentation;

use OpenTelemetry\API\Trace\SpanKind;

final class HookDefinition
{
    /**
     * @param class-string $class
     * @param string $method
     * @param string|null $spanName Override span name (default: FQCN::method)
     * @param int $kind SpanKind constant (default: KIND_INTERNAL)
     * @param array<string, mixed> $attributes Static attributes to set on every span
     * @param (\Closure(object|null, array, string, string): array<string, mixed>)|null $attributeCallback
     *     Dynamic attributes callback: fn($instance, $params, $class, $function) => ['key' => 'value']; $instance may be null for static methods
     */
    private const VALID_SPAN_KINDS = [
        SpanKind::KIND_INTERNAL,
        SpanKind::KIND_SERVER,
        SpanKind::KIND_CLIENT,
        SpanKind::KIND_PRODUCER,
        SpanKind::KIND_CONSUMER,
    ];

    public function __construct(
        public readonly string $class,
        public readonly string $method,
        public readonly ?string $spanName = null,
        public readonly int $kind = SpanKind::KIND_INTERNAL,
        public readonly array $attributes = [],
        public readonly ?\Closure $attributeCallback = null,
    ) {
        if (!in_array($kind, self::VALID_SPAN_KINDS, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid SpanKind value: %d. Use SpanKind::KIND_* constants.', $kind)
            );
        }
    }
}
