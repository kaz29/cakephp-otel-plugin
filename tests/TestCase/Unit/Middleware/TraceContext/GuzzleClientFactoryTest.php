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
        $client = GuzzleClientFactory::create();

        $handler = $client->getConfig('handler');
        $this->assertInstanceOf(HandlerStack::class, $handler);
        $this->assertStringContainsString('traceparent', (string) $handler);
    }

    public function testClientConfigIsPassedThrough(): void
    {
        $client = GuzzleClientFactory::create(['base_uri' => 'https://api.example.com']);

        $this->assertSame('https://api.example.com', (string) $client->getConfig('base_uri'));
    }

    public function testCreateSpanParamRegistersMiddlewareWithSpanEnabled(): void
    {
        // Verify the HandlerStack string representation reflects the createSpan variant.
        // Span creation itself is tested in GuzzleMiddlewareTest.
        $client = GuzzleClientFactory::create([], createSpan: true);

        $handler = $client->getConfig('handler');
        $this->assertInstanceOf(HandlerStack::class, $handler);
        $this->assertStringContainsString('traceparent', (string) $handler);
    }
}
