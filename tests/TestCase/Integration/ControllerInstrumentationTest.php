<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Integration;

use Cake\Controller\Controller;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Core\Configure;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use PHPUnit\Framework\TestCase;

class ControllerInstrumentationTest extends TestCase
{
    private InMemoryExporter $exporter;
    private static bool $instrumentationLoaded = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!self::$instrumentationLoaded) {
            require_once dirname(__DIR__, 3) . '/src/Instrumentation/ControllerInstrumentation.php';
            self::$instrumentationLoaded = true;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        Configure::write('App.encoding', 'UTF-8');

        $this->exporter = new InMemoryExporter();
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new SimpleSpanProcessor($this->exporter))
            ->build();

        Globals::reset();
        Globals::registerInitializer(function (Configurator $configurator) use ($tracerProvider) {
            return $configurator->withTracerProvider($tracerProvider);
        });
    }

    public function testInvokeActionCreatesSpanWithCorrectAttributes(): void
    {
        $request = new ServerRequest([
            'url' => '/articles/index',
            'environment' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/articles/index',
            ],
        ]);
        $request = $request->withParam('action', 'index');

        $controller = new class ($request) extends Controller {
            public function index(): void
            {
            }
        };

        $controller->invokeAction(fn () => $controller->getResponse(), []);

        $spans = $this->exporter->getSpans();
        $this->assertGreaterThanOrEqual(1, count($spans));

        $span = $spans[count($spans) - 1];
        $this->assertStringContainsString('::index', $span->getName());
        $this->assertSame(SpanKind::KIND_SERVER, $span->getKind());
        $this->assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());

        $attributes = $span->getAttributes()->toArray();
        $this->assertSame('GET', $attributes['http.method']);
        $this->assertSame('index', $attributes['cake.action']);
        $this->assertArrayHasKey('cake.controller', $attributes);
    }

    public function testInvokeActionRecordsExceptionOnError(): void
    {
        $request = new ServerRequest([
            'url' => '/articles/fail',
            'environment' => [
                'REQUEST_METHOD' => 'POST',
                'REQUEST_URI' => '/articles/fail',
            ],
        ]);
        $request = $request->withParam('action', 'fail');

        $controller = new class ($request) extends Controller {
            public function fail(): void
            {
            }
        };

        $exception = new \RuntimeException('Something went wrong');

        try {
            $controller->invokeAction(function () use ($exception) {
                throw $exception;
            }, []);
        } catch (\RuntimeException) {
            // expected
        }

        $spans = $this->exporter->getSpans();
        $this->assertGreaterThanOrEqual(1, count($spans));

        $span = $spans[count($spans) - 1];
        $this->assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        $this->assertStringContainsString('Something went wrong', $span->getStatus()->getDescription());
    }
}
