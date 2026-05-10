<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Integration;

use Cake\Controller\Controller;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Core\Configure;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OtelInstrumentation\Instrumentation\ExclusionRegistry;
use OtelInstrumentation\Test\TestCase\OtelTestTrait;
use PHPUnit\Framework\TestCase;

class ControllerInstrumentationTest extends TestCase
{
    use OtelTestTrait;

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

        if (!extension_loaded('opentelemetry')) {
            $this->markTestSkipped('ext-opentelemetry is not installed.');
        }

        Configure::write('App.encoding', 'UTF-8');
        $this->setUpOtel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        ExclusionRegistry::reset();
        $this->resetOtel();
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

        $spans = $this->getSpans();
        $this->assertGreaterThanOrEqual(1, count($spans));

        $span = $spans[count($spans) - 1];
        $this->assertStringContainsString('::index', $span->getName());
        $this->assertSame(SpanKind::KIND_SERVER, $span->getKind());
        $this->assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());

        $this->assertSame('GET', $this->getSpanAttribute($span, 'http.method'));
        $this->assertSame('index', $this->getSpanAttribute($span, 'cake.action'));
        $this->assertNotNull($this->getSpanAttribute($span, 'cake.controller'));
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

        $spans = $this->getSpans();
        $this->assertGreaterThanOrEqual(1, count($spans));

        $span = $spans[count($spans) - 1];
        $this->assertSame(StatusCode::STATUS_ERROR, $span->getStatus()->getCode());
        $this->assertStringContainsString('Something went wrong', $span->getStatus()->getDescription());
    }
}
