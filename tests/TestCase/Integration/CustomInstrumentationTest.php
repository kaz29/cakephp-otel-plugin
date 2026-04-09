<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Integration;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OtelInstrumentation\Instrumentation\CustomInstrumentation;
use OtelInstrumentation\Test\TestCase\OtelTestTrait;
use PHPUnit\Framework\TestCase;

class DummyService
{
    public function doWork(string $input): string
    {
        return 'done: ' . $input;
    }

    public function failingMethod(): void
    {
        throw new \RuntimeException('service error');
    }

    public function withArgs(int $amount, string $currency = 'USD'): string
    {
        return "{$amount} {$currency}";
    }
}

class CustomInstrumentationTest extends TestCase
{
    use OtelTestTrait;

    private static bool $instrumentationLoaded = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!extension_loaded('opentelemetry')) {
            return;
        }

        if (!self::$instrumentationLoaded) {
            CustomInstrumentation::register(
                DummyService::class,
                'doWork',
            );
            CustomInstrumentation::register(
                DummyService::class,
                'failingMethod',
                spanName: 'custom.failing',
                kind: SpanKind::KIND_CLIENT,
                attributes: ['service.name' => 'dummy'],
            );
            CustomInstrumentation::register(
                DummyService::class,
                'withArgs',
                spanName: 'custom.withArgs',
                attributeCallback: function (mixed $instance, array $params, string $class, string $function): array {
                    return [
                        'payment.amount' => $params[0] ?? null,
                        'payment.currency' => $params[1] ?? 'USD',
                    ];
                },
            );
            CustomInstrumentation::apply();
            self::$instrumentationLoaded = true;
        }
    }

    public static function tearDownAfterClass(): void
    {
        CustomInstrumentation::reset();
        self::$instrumentationLoaded = false;
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('opentelemetry')) {
            $this->markTestSkipped('ext-opentelemetry is not installed.');
        }

        $this->setUpOtel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetOtel();
    }

    public function testDefaultSpanNameAndKind(): void
    {
        $svc = new DummyService();
        $svc->doWork('hello');

        $spans = $this->getSpansByName(DummyService::class . '::doWork');
        $this->assertCount(1, $spans);

        $span = $spans[0];
        $this->assertSame(SpanKind::KIND_INTERNAL, $span->getKind());
        $this->assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());
    }

    public function testCustomSpanNameKindAndAttributes(): void
    {
        $svc = new DummyService();
        try {
            $svc->failingMethod();
        } catch (\RuntimeException) {
            // expected
        }

        $spans = $this->getSpansByName('custom.failing');
        $this->assertCount(1, $spans);

        $span = $spans[0];
        $this->assertSame(SpanKind::KIND_CLIENT, $span->getKind());
        $this->assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        $this->assertStringContainsString('service error', $span->getStatus()->getDescription());
        $this->assertSame('dummy', $this->getSpanAttribute($span, 'service.name'));
    }

    public function testAttributeCallback(): void
    {
        $svc = new DummyService();
        $svc->withArgs(1000, 'JPY');

        $spans = $this->getSpansByName('custom.withArgs');
        $this->assertCount(1, $spans);

        $span = $spans[0];
        $this->assertSame(1000, $this->getSpanAttribute($span, 'payment.amount'));
        $this->assertSame('JPY', $this->getSpanAttribute($span, 'payment.currency'));
    }
}
