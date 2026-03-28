<?php
declare(strict_types=1);

namespace OtelInstrumentation\Middleware;

use Cake\Http\Exception\HttpException;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Logs\Severity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OtelErrorLoggingMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $exception) {
            if (!$this->shouldLog($exception)) {
                throw $exception;
            }

            $logger = Globals::loggerProvider()->getLogger('otel-instrumentation.cakephp.error');

            $logger->logRecordBuilder()
                ->setSeverityNumber(Severity::ERROR)
                ->setSeverityText('ERROR')
                ->setBody($exception->getMessage())
                ->setAttribute('exception.type', get_class($exception))
                ->setAttribute('exception.message', $exception->getMessage())
                ->setAttribute('exception.stacktrace', $exception->getTraceAsString())
                ->emit();

            throw $exception;
        }
    }

    private function shouldLog(\Throwable $exception): bool
    {
        if ($exception instanceof HttpException) {
            return $exception->getCode() >= 500;
        }

        return true;
    }
}
