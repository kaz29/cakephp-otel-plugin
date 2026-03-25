<?php
declare(strict_types=1);

use Cake\Controller\Controller;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;

$instrumentation = new CachedInstrumentation('otel-instrumentation.cakephp.controller');

\OpenTelemetry\Instrumentation\hook(
    class: Controller::class,
    function: 'invokeAction',
    pre: static function (Controller $controller, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
        $request = $controller->getRequest();
        $action = $request->getParam('action', 'unknown');

        $span = $instrumentation->tracer()
            ->spanBuilder(get_class($controller) . '::' . $action)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute('http.method', $request->getMethod())
            ->setAttribute('http.url', (string) $request->getUri())
            ->setAttribute('http.route', $action)
            ->setAttribute('cake.controller', get_class($controller))
            ->setAttribute('cake.action', $action)
            ->startSpan();

        Context::storage()->attach($span->storeInContext(Context::getCurrent()));
    },
    post: static function (Controller $controller, array $params, mixed $returnValue, ?\Throwable $exception): void {
        $scope = Context::storage()->scope();
        if ($scope === null) {
            return;
        }

        $scope->detach();
        $span = Span::fromContext($scope->context());

        if ($exception !== null) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        $span->end();
    }
);
