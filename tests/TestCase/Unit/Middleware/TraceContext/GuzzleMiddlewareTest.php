<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Unit\Middleware\TraceContext;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OtelInstrumentation\Middleware\TraceContext\GuzzleMiddleware;
use OtelInstrumentation\Test\TestCase\OtelTestTrait;
use PHPUnit\Framework\TestCase;

class GuzzleMiddlewareTest extends TestCase
{
    use OtelTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOtel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetOtel();
    }

    /** @param array<mixed> $history */
    private function buildClient(GuzzleMiddleware $middleware, MockHandler $mock, array &$history): Client
    {
        $stack = HandlerStack::create($mock);
        $stack->push($middleware, 'traceparent');
        $stack->push(Middleware::history($history));

        return new Client(['handler' => $stack]);
    }

    public function testInjectsTraceparentWhenActiveSpanExists(): void
    {
        $tracer = \OpenTelemetry\API\Globals::tracerProvider()->getTracer('test');
        $span = $tracer->spanBuilder('test-span')->startSpan();
        $scope = $span->storeInContext(Context::getCurrent())->activate();

        $history = [];
        $client = $this->buildClient(new GuzzleMiddleware(), new MockHandler([new Response(200)]), $history);
        $client->get('https://example.com/');

        $scope->detach();
        $span->end();

        $sentRequest = $history[0]['request'];
        $this->assertTrue($sentRequest->hasHeader('traceparent'));
        $this->assertMatchesRegularExpression(
            '/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/',
            $sentRequest->getHeaderLine('traceparent'),
        );
    }

    public function testDoesNotInjectTraceparentWhenNoActiveSpan(): void
    {
        $history = [];
        $client = $this->buildClient(new GuzzleMiddleware(), new MockHandler([new Response(200)]), $history);
        $client->get('https://example.com/');

        $this->assertFalse($history[0]['request']->hasHeader('traceparent'));
    }

    public function testPreservesExistingRequestHeaders(): void
    {
        $history = [];
        $client = $this->buildClient(new GuzzleMiddleware(), new MockHandler([new Response(200)]), $history);
        $client->get('https://example.com/', ['headers' => ['Authorization' => 'Bearer token123']]);

        $this->assertSame('Bearer token123', $history[0]['request']->getHeaderLine('Authorization'));
    }

    public function testPassesRequestThrough(): void
    {
        $history = [];
        $client = $this->buildClient(new GuzzleMiddleware(), new MockHandler([new Response(201)]), $history);
        $response = $client->get('https://example.com/');

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testCreatesClientSpanWhenEnabled(): void
    {
        $history = [];
        $client = $this->buildClient(
            new GuzzleMiddleware(createSpan: true),
            new MockHandler([new Response(200)]),
            $history,
        );
        $client->get('https://api.example.com/users');

        $spans = $this->getSpans();
        $this->assertCount(1, $spans);
        $this->assertSame(SpanKind::KIND_CLIENT, $spans[0]->getKind());
        $this->assertSame('GET api.example.com', $spans[0]->getName());
        $this->assertSame(StatusCode::STATUS_OK, $spans[0]->getStatus()->getCode());
        $this->assertSame('GET', $this->getSpanAttribute($spans[0], 'http.request.method'));
        $this->assertSame('api.example.com', $this->getSpanAttribute($spans[0], 'server.address'));
        $this->assertSame(200, $this->getSpanAttribute($spans[0], 'http.response.status_code'));
    }

    public function testSpanEndsWithErrorOnConnectException(): void
    {
        $history = [];
        $exception = new ConnectException('Connection refused', new Request('GET', 'https://example.com/'));
        $client = $this->buildClient(
            new GuzzleMiddleware(createSpan: true),
            new MockHandler([$exception]),
            $history,
        );

        try {
            $client->get('https://example.com/');
        } catch (ConnectException) {
        }

        $spans = $this->getSpans();
        $this->assertCount(1, $spans);
        $this->assertSame(StatusCode::STATUS_ERROR, $spans[0]->getStatus()->getCode());
    }

    public function testNoSpanCreatedWhenCreateSpanFalse(): void
    {
        $history = [];
        $client = $this->buildClient(
            new GuzzleMiddleware(createSpan: false),
            new MockHandler([new Response(200)]),
            $history,
        );
        $client->get('https://example.com/');

        $this->assertCount(0, $this->getSpans());
    }
}
