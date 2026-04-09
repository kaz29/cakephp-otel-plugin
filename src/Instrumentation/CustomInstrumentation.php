<?php
declare(strict_types=1);

namespace OtelInstrumentation\Instrumentation;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;

final class CustomInstrumentation
{
    /** @var HookDefinition[] */
    private static array $definitions = [];

    private static bool $applied = false;

    /**
     * Register a hook for a class method.
     *
     * @param class-string $class
     * @param string $method
     * @param string|null $spanName Override span name (default: FQCN::method)
     * @param int $kind SpanKind constant (default: KIND_INTERNAL)
     * @param array<string, mixed> $attributes Static attributes
     * @param (\Closure(object, array, string, string): array<string, mixed>)|null $attributeCallback
     */
    public static function register(
        string $class,
        string $method,
        ?string $spanName = null,
        int $kind = SpanKind::KIND_INTERNAL,
        array $attributes = [],
        ?\Closure $attributeCallback = null,
    ): void {
        self::$definitions[] = new HookDefinition(
            class: $class,
            method: $method,
            spanName: $spanName,
            kind: $kind,
            attributes: $attributes,
            attributeCallback: $attributeCallback,
        );
    }

    /**
     * Register from a HookDefinition directly.
     */
    public static function add(HookDefinition $definition): void
    {
        self::$definitions[] = $definition;
    }

    /**
     * Load hook definitions from Configure-style array format.
     *
     * @param array<array{class: class-string, method: string, spanName?: string, kind?: int, attributes?: array<string, mixed>}> $configs
     */
    public static function loadFromConfig(array $configs): void
    {
        foreach ($configs as $config) {
            self::$definitions[] = new HookDefinition(
                class: $config['class'],
                method: $config['method'],
                spanName: $config['spanName'] ?? null,
                kind: $config['kind'] ?? SpanKind::KIND_INTERNAL,
                attributes: $config['attributes'] ?? [],
                attributeCallback: $config['attributeCallback'] ?? null,
            );
        }
    }

    /**
     * Apply all registered hooks via \OpenTelemetry\Instrumentation\hook().
     * Called once during Plugin::bootstrap(). Idempotent.
     */
    public static function apply(): void
    {
        if (self::$applied) {
            return;
        }
        self::$applied = true;

        $instrumentation = new CachedInstrumentation('otel-instrumentation.cakephp.custom');

        foreach (self::$definitions as $definition) {
            $def = $definition;

            \OpenTelemetry\Instrumentation\hook(
                class: $def->class,
                function: $def->method,
                pre: static function (
                    mixed $instance,
                    array $params,
                    string $class,
                    string $function,
                    ?string $filename,
                    ?int $lineno,
                ) use ($instrumentation, $def): void {
                    $spanBuilder = $instrumentation->tracer()
                        ->spanBuilder($def->spanName ?? ($class . '::' . $function))
                        ->setSpanKind($def->kind);

                    foreach ($def->attributes as $key => $value) {
                        $spanBuilder->setAttribute($key, $value);
                    }

                    if ($def->attributeCallback !== null) {
                        $dynamicAttrs = ($def->attributeCallback)($instance, $params, $class, $function);
                        foreach ($dynamicAttrs as $key => $value) {
                            $spanBuilder->setAttribute($key, $value);
                        }
                    }

                    $span = $spanBuilder->startSpan();
                    Context::storage()->attach($span->storeInContext(Context::getCurrent()));
                },
                post: static function (
                    mixed $instance,
                    array $params,
                    mixed $returnValue,
                    ?\Throwable $exception,
                ): void {
                    $scope = Context::storage()->scope();
                    if ($scope === null) {
                        return;
                    }

                    $scope->detach();
                    $span = Span::fromContext($scope->context());

                    if ($exception !== null) {
                        $span->recordException($exception);
                        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                    } else {
                        $span->setStatus(StatusCode::STATUS_OK);
                    }

                    $span->end();
                },
            );
        }
    }

    /**
     * Reset state (for testing).
     */
    public static function reset(): void
    {
        self::$definitions = [];
        self::$applied = false;
    }
}
