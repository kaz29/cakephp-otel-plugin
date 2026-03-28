<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Integration\Middleware;

use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\ServiceUnavailableException;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use OtelInstrumentation\Middleware\OtelErrorLoggingMiddleware;
use OtelInstrumentation\Test\TestCase\OtelTestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OtelErrorLoggingMiddlewareTest extends TestCase
{
    use OtelTestTrait;

    private OtelErrorLoggingMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('opentelemetry')) {
            $this->markTestSkipped('ext-opentelemetry is not installed.');
        }

        $this->setUpOtel();
        $this->middleware = new OtelErrorLoggingMiddleware();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetOtel();
    }

    public function testEmitsLogFor500Exception(): void
    {
        $handler = $this->createHandler(new InternalErrorException('DB connection failed'));

        try {
            $this->middleware->process(new ServerRequest(), $handler);
        } catch (InternalErrorException) {
        }

        $logs = $this->getLogRecords();
        $this->assertCount(1, $logs);

        $log = $logs[0];
        $this->assertSame('ERROR', $log->getSeverityText());
        $this->assertSame('DB connection failed', $log->getBody());

        $attributes = $log->getAttributes()->toArray();
        $this->assertSame(InternalErrorException::class, $attributes['exception.type']);
        $this->assertSame('DB connection failed', $attributes['exception.message']);
        $this->assertArrayHasKey('exception.stacktrace', $attributes);
    }

    public function testEmitsLogFor503Exception(): void
    {
        $handler = $this->createHandler(new ServiceUnavailableException('Maintenance'));

        try {
            $this->middleware->process(new ServerRequest(), $handler);
        } catch (ServiceUnavailableException) {
        }

        $logs = $this->getLogRecords();
        $this->assertCount(1, $logs);
    }

    public function testDoesNotEmitLogFor404Exception(): void
    {
        $handler = $this->createHandler(new NotFoundException('Page not found'));

        try {
            $this->middleware->process(new ServerRequest(), $handler);
        } catch (NotFoundException) {
        }

        $logs = $this->getLogRecords();
        $this->assertCount(0, $logs);
    }

    public function testEmitsLogForNonHttpException(): void
    {
        $handler = $this->createHandler(new \RuntimeException('Unexpected error'));

        try {
            $this->middleware->process(new ServerRequest(), $handler);
        } catch (\RuntimeException) {
        }

        $logs = $this->getLogRecords();
        $this->assertCount(1, $logs);

        $log = $logs[0];
        $attributes = $log->getAttributes()->toArray();
        $this->assertSame(\RuntimeException::class, $attributes['exception.type']);
    }

    public function testRethrowsException(): void
    {
        $exception = new InternalErrorException('Fail');
        $handler = $this->createHandler($exception);

        $this->expectException(InternalErrorException::class);
        $this->expectExceptionMessage('Fail');

        $this->middleware->process(new ServerRequest(), $handler);
    }

    public function testPassesThroughOnSuccess(): void
    {
        $handler = $this->createHandler(null);

        $response = $this->middleware->process(new ServerRequest(), $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $this->getLogRecords());
    }

    private function createHandler(?\Throwable $exception): RequestHandlerInterface
    {
        return new class ($exception) implements RequestHandlerInterface {
            public function __construct(private readonly ?\Throwable $exception)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                if ($this->exception !== null) {
                    throw $this->exception;
                }

                return new Response();
            }
        };
    }
}
