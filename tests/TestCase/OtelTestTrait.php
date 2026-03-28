<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase;

use ArrayObject;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\SDK\Logs\Exporter\InMemoryExporter as LogInMemoryExporter;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter as SpanInMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;

trait OtelTestTrait
{
    private SpanInMemoryExporter $spanExporter;
    private LogInMemoryExporter $logExporter;

    protected function setUpOtel(): void
    {
        $this->spanExporter = new SpanInMemoryExporter();
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new SimpleSpanProcessor($this->spanExporter))
            ->build();

        $this->logExporter = new LogInMemoryExporter();
        $loggerProvider = LoggerProvider::builder()
            ->addLogRecordProcessor(new SimpleLogRecordProcessor($this->logExporter))
            ->build();

        Globals::reset();
        Globals::registerInitializer(function (Configurator $configurator) use ($tracerProvider, $loggerProvider) {
            return $configurator
                ->withTracerProvider($tracerProvider)
                ->withLoggerProvider($loggerProvider);
        });
    }

    protected function resetOtel(): void
    {
        Globals::reset();
    }

    /**
     * @return array<\OpenTelemetry\SDK\Trace\ImmutableSpan>
     */
    protected function getSpans(): array
    {
        return $this->spanExporter->getSpans();
    }

    /**
     * @return array<\OpenTelemetry\SDK\Trace\ImmutableSpan>
     */
    protected function getSpansByName(string $name): array
    {
        return array_values(array_filter(
            $this->getSpans(),
            fn ($span) => $span->getName() === $name,
        ));
    }

    protected function getFirstSpan(): ?\OpenTelemetry\SDK\Trace\ImmutableSpan
    {
        $spans = $this->getSpans();

        return $spans[0] ?? null;
    }

    protected function getSpanAttribute(\OpenTelemetry\SDK\Trace\ImmutableSpan $span, string $key): mixed
    {
        return $span->getAttributes()->toArray()[$key] ?? null;
    }

    protected function getLogRecords(): ArrayObject
    {
        return $this->logExporter->getStorage();
    }
}
