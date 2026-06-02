<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Unit\Middleware\TraceContext;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use OtelInstrumentation\Middleware\TraceContext\GuzzleClientFactory;
use OtelInstrumentation\Test\TestCase\OtelTestTrait;
use PHPUnit\Framework\TestCase;

class GuzzleClientFactoryTest extends TestCase
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

    public function testCreateReturnsGuzzleClient(): void
    {
        $client = GuzzleClientFactory::create();

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testMiddlewareIsRegisteredInStack(): void
    {
        $stack = HandlerStack::create();
        $stack->push(new \OtelInstrumentation\Middleware\TraceContext\GuzzleMiddleware(), 'traceparent');

        $this->assertStringContainsString('traceparent', (string) $stack);
    }

    public function testClientConfigIsPassedThrough(): void
    {
        $client = GuzzleClientFactory::create(['base_uri' => 'https://api.example.com']);

        $this->assertInstanceOf(Client::class, $client);
    }

    public function testCreateSpanParamRegistersMiddlewareWithSpanEnabled(): void
    {
        $stack = HandlerStack::create();
        $stack->push(new \OtelInstrumentation\Middleware\TraceContext\GuzzleMiddleware(createSpan: true), 'traceparent');

        $this->assertStringContainsString('traceparent', (string) $stack);
    }

    public function testCustomSpanNameIsPassedThrough(): void
    {
        $history = [];
        $stack = HandlerStack::create(new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(200)]));
        $stack->push(new \OtelInstrumentation\Middleware\TraceContext\GuzzleMiddleware(createSpan: true, spanName: 'external.payment-api'), 'traceparent');
        $client = new \GuzzleHttp\Client(['handler' => $stack]);
        $client->get('https://api.example.com/users');

        $spans = $this->getSpans();
        $this->assertCount(1, $spans);
        $this->assertSame('external.payment-api', $spans[0]->getName());
    }

    public function testThrowsWhenHandlerKeyProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        GuzzleClientFactory::create(['handler' => HandlerStack::create()]);
    }
}
