<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Unit\Log;

use OpenTelemetry\API\Trace\NonRecordingSpan;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\Context\Context;
use OtelInstrumentation\Log\TraceAwareLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class TraceAwareLoggerTest extends TestCase
{
    public function testAddsTraceIdAndSpanIdWhenSpanIsActive(): void
    {
        $innerLogger = $this->createMock(LoggerInterface::class);

        $spanContext = SpanContext::create(
            'abcdef1234567890abcdef1234567890',
            'abcdef1234567890',
            TraceFlags::SAMPLED
        );

        $span = new NonRecordingSpan($spanContext);
        $context = Context::getCurrent()->withContextValue($span);
        $scope = $context->activate();

        $innerLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::INFO,
                'Test message',
                $this->callback(function (array $context) {
                    return $context['trace_id'] === 'abcdef1234567890abcdef1234567890'
                        && $context['span_id'] === 'abcdef1234567890'
                        && $context['user_id'] === 42;
                })
            );

        $logger = new TraceAwareLogger($innerLogger);
        $logger->info('Test message', ['user_id' => 42]);

        $scope->detach();
    }

    public function testDoesNotAddTraceIdWhenNoActiveSpan(): void
    {
        $innerLogger = $this->createMock(LoggerInterface::class);

        $innerLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::WARNING,
                'No span message',
                $this->callback(function (array $context) {
                    return !isset($context['trace_id'])
                        && !isset($context['span_id'])
                        && $context['key'] === 'value';
                })
            );

        $logger = new TraceAwareLogger($innerLogger);
        $logger->warning('No span message', ['key' => 'value']);
    }

    public function testPassesMessageAndOriginalContextToDelegate(): void
    {
        $innerLogger = $this->createMock(LoggerInterface::class);

        $originalContext = ['foo' => 'bar', 'baz' => 123];

        $innerLogger->expects($this->once())
            ->method('log')
            ->with(
                LogLevel::ERROR,
                'Error occurred',
                $this->callback(function (array $context) {
                    return $context['foo'] === 'bar' && $context['baz'] === 123;
                })
            );

        $logger = new TraceAwareLogger($innerLogger);
        $logger->error('Error occurred', $originalContext);
    }
}
