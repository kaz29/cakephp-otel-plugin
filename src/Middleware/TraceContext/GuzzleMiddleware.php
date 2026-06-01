<?php
declare(strict_types=1);

namespace OtelInstrumentation\Middleware\TraceContext;

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Context;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Guzzle middleware that injects W3C traceparent (and tracestate) headers into
 * outbound HTTP requests, propagating the current OpenTelemetry trace context.
 *
 * Requires guzzlehttp/guzzle ^7.0 in your application's composer.json.
 *
 * Usage (via DI factory):
 * @see GuzzleClientFactory
 *
 * Usage (manual HandlerStack):
 *   $stack = HandlerStack::create();
 *   $stack->push(new GuzzleMiddleware(), 'traceparent');
 *   $client = new Client(['handler' => $stack]);
 */
class GuzzleMiddleware
{
    /**
     * @param bool $createSpan When true, a KIND_CLIENT span is created for each
     *                         outbound request. Default false: inject headers only.
     */
    public function __construct(private readonly bool $createSpan = false) {}

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            // PSR-7 RequestInterface is immutable; inject into a plain array first
            // then copy onto the request via withHeader().
            $headers = [];
            TraceContextPropagator::getInstance()->inject($headers);
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }

            if (!$this->createSpan) {
                return $handler($request, $options);
            }

            return $this->withSpan($request, $options, $handler);
        };
    }

    private function withSpan(RequestInterface $request, array $options, callable $handler): PromiseInterface
    {
        $method = strtoupper($request->getMethod());
        $uri = $request->getUri();

        $span = Globals::tracerProvider()
            ->getTracer('otel-instrumentation.cakephp.guzzle')
            ->spanBuilder($method . ' ' . $uri->getHost())
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.request.method', $method)
            ->setAttribute('server.address', $uri->getHost())
            ->setAttribute('url.full', (string) $uri)
            ->startSpan();

        $scope = $span->storeInContext(Context::getCurrent())->activate();

        return $handler($request, $options)->then(
            function (ResponseInterface $response) use ($span, $scope): ResponseInterface {
                $span->setAttribute('http.response.status_code', $response->getStatusCode());
                $span->setStatus(StatusCode::STATUS_OK);
                $span->end();
                $scope->detach();

                return $response;
            },
            function (mixed $reason) use ($span, $scope): PromiseInterface {
                if ($reason instanceof \Throwable) {
                    $span->recordException($reason);
                    $span->setStatus(StatusCode::STATUS_ERROR, $reason->getMessage());
                }
                $span->end();
                $scope->detach();

                return Create::rejectionFor($reason);
            },
        );
    }
}
