<?php
declare(strict_types=1);

namespace OtelInstrumentation\Log;

use OpenTelemetry\API\Trace\Span;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class TraceAwareLogger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $spanContext = Span::getCurrent()->getContext();
        $traceId = $spanContext->getTraceId();

        if ($traceId !== '00000000000000000000000000000000') {
            $context['trace_id'] = $traceId;
            $context['span_id'] = $spanContext->getSpanId();
        }

        $this->logger->log($level, $message, $context);
    }
}
