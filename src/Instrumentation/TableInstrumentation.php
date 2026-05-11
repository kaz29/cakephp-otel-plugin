<?php
declare(strict_types=1);

use Cake\ORM\Table;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OtelInstrumentation\Instrumentation\ExclusionRegistry;
use OtelInstrumentation\Util\DbSystemResolver;

$instrumentation = new CachedInstrumentation('otel-instrumentation.cakephp.model');

// Table::find
\OpenTelemetry\Instrumentation\hook(
    class: Table::class,
    function: 'find',
    pre: static function (Table $table, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
        if (ExclusionRegistry::isCurrentlyExcluded()) {
            return;
        }

        $findType = $params[0] ?? 'all';
        $alias = $table->getAlias();

        $span = $instrumentation->tracer()
            ->spanBuilder($alias . '.find(' . $findType . ')')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('db.system', DbSystemResolver::resolveFromTable($table))
            ->setAttribute('cake.table', $alias)
            ->setAttribute('cake.find_type', $findType)
            ->startSpan();

        Context::storage()->attach($span->storeInContext(Context::getCurrent()));
    },
    post: static function (Table $table, array $params, mixed $returnValue, ?\Throwable $exception): void {
        if (ExclusionRegistry::isCurrentlyExcluded()) {
            return;
        }

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

// Table::save
\OpenTelemetry\Instrumentation\hook(
    class: Table::class,
    function: 'save',
    pre: static function (Table $table, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
        if (ExclusionRegistry::isCurrentlyExcluded()) {
            return;
        }

        $alias = $table->getAlias();
        $entity = $params[0] ?? null;
        $isNew = $entity !== null && method_exists($entity, 'isNew') ? $entity->isNew() : null;

        $spanBuilder = $instrumentation->tracer()
            ->spanBuilder($alias . '.save')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('db.system', DbSystemResolver::resolveFromTable($table))
            ->setAttribute('cake.table', $alias);

        if ($isNew !== null) {
            $spanBuilder->setAttribute('cake.entity.new', $isNew);
        }

        $span = $spanBuilder->startSpan();
        Context::storage()->attach($span->storeInContext(Context::getCurrent()));
    },
    post: static function (Table $table, array $params, mixed $returnValue, ?\Throwable $exception): void {
        if (ExclusionRegistry::isCurrentlyExcluded()) {
            return;
        }

        $scope = Context::storage()->scope();
        if ($scope === null) {
            return;
        }

        $scope->detach();
        $span = Span::fromContext($scope->context());

        if ($exception !== null) {
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        } elseif ($returnValue === false) {
            $span->setStatus(StatusCode::STATUS_ERROR, 'save() returned false');
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        $span->end();
    }
);

// Table::delete
\OpenTelemetry\Instrumentation\hook(
    class: Table::class,
    function: 'delete',
    pre: static function (Table $table, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation): void {
        if (ExclusionRegistry::isCurrentlyExcluded()) {
            return;
        }

        $alias = $table->getAlias();

        $span = $instrumentation->tracer()
            ->spanBuilder($alias . '.delete')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('db.system', DbSystemResolver::resolveFromTable($table))
            ->setAttribute('cake.table', $alias)
            ->startSpan();

        Context::storage()->attach($span->storeInContext(Context::getCurrent()));
    },
    post: static function (Table $table, array $params, mixed $returnValue, ?\Throwable $exception): void {
        if (ExclusionRegistry::isCurrentlyExcluded()) {
            return;
        }

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
