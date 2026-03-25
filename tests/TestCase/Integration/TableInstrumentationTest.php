<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Integration;

use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use PHPUnit\Framework\TestCase;

class TableInstrumentationTest extends TestCase
{
    private InMemoryExporter $exporter;
    private Table $articlesTable;
    private static bool $instrumentationLoaded = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!self::$instrumentationLoaded) {
            require_once dirname(__DIR__, 3) . '/src/Instrumentation/TableInstrumentation.php';
            self::$instrumentationLoaded = true;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->exporter = new InMemoryExporter();
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new SimpleSpanProcessor($this->exporter))
            ->build();

        Globals::reset();
        Globals::registerInitializer(function (Configurator $configurator) use ($tracerProvider) {
            return $configurator->withTracerProvider($tracerProvider);
        });

        $this->articlesTable = TableRegistry::getTableLocator()->get('OtelTestArticles', [
            'className' => Table::class,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        TableRegistry::getTableLocator()->clear();
    }

    public function testFindCreatesSpanWithCorrectAttributes(): void
    {
        $this->articlesTable->find('all');

        $spans = $this->exporter->getSpans();
        $this->assertGreaterThanOrEqual(1, count($spans));

        $span = $spans[count($spans) - 1];
        $this->assertSame('OtelTestArticles.find(all)', $span->getName());
        $this->assertSame(SpanKind::KIND_CLIENT, $span->getKind());
        $this->assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());

        $attributes = $span->getAttributes()->toArray();
        $this->assertSame('postgresql', $attributes['db.system']);
        $this->assertSame('OtelTestArticles', $attributes['cake.table']);
        $this->assertSame('all', $attributes['cake.find_type']);
    }

    public function testFindListCreatesSpanWithListType(): void
    {
        $this->articlesTable->find('list');

        $spans = $this->exporter->getSpans();
        $this->assertGreaterThanOrEqual(1, count($spans));

        $span = $spans[count($spans) - 1];
        $this->assertSame('OtelTestArticles.find(list)', $span->getName());

        $attributes = $span->getAttributes()->toArray();
        $this->assertSame('list', $attributes['cake.find_type']);
    }

    public function testSaveNewEntityCreatesSpan(): void
    {
        $entity = $this->articlesTable->newEntity([
            'title' => 'Test Article',
            'body' => 'Test body',
        ]);

        $result = $this->articlesTable->save($entity);

        $spans = $this->exporter->getSpans();
        $saveSpans = array_values(array_filter($spans, fn ($s) => str_contains($s->getName(), '.save')));
        $this->assertNotEmpty($saveSpans);

        $span = end($saveSpans);
        $this->assertSame('OtelTestArticles.save', $span->getName());
        $this->assertSame(SpanKind::KIND_CLIENT, $span->getKind());
        $this->assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());

        $attributes = $span->getAttributes()->toArray();
        $this->assertSame('postgresql', $attributes['db.system']);
        $this->assertSame('OtelTestArticles', $attributes['cake.table']);
        $this->assertTrue($attributes['cake.entity.new']);

        // Cleanup
        if ($result) {
            $this->articlesTable->delete($result);
        }
    }

    public function testDeleteCreatesSpan(): void
    {
        $entity = $this->articlesTable->newEntity([
            'title' => 'To Delete',
            'body' => 'Will be deleted',
        ]);
        $saved = $this->articlesTable->save($entity);
        $this->assertNotFalse($saved);

        // Reset exporter to only capture delete span
        $this->exporter = new InMemoryExporter();
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new SimpleSpanProcessor($this->exporter))
            ->build();

        Globals::reset();
        Globals::registerInitializer(function (Configurator $configurator) use ($tracerProvider) {
            return $configurator->withTracerProvider($tracerProvider);
        });

        $this->articlesTable->delete($saved);

        $spans = $this->exporter->getSpans();
        $deleteSpans = array_values(array_filter($spans, fn ($s) => str_contains($s->getName(), '.delete')));
        $this->assertNotEmpty($deleteSpans);

        $span = end($deleteSpans);
        $this->assertSame('OtelTestArticles.delete', $span->getName());
        $this->assertSame(SpanKind::KIND_CLIENT, $span->getKind());

        $attributes = $span->getAttributes()->toArray();
        $this->assertSame('OtelTestArticles', $attributes['cake.table']);
    }

    public function testSaveWithDbSystemAttribute(): void
    {
        $entity = $this->articlesTable->newEntity([
            'title' => 'DB System Test',
            'body' => 'Testing db.system attribute',
        ]);

        $result = $this->articlesTable->save($entity);

        $spans = $this->exporter->getSpans();
        $saveSpans = array_values(array_filter($spans, fn ($s) => str_contains($s->getName(), '.save')));
        $this->assertNotEmpty($saveSpans);

        $span = end($saveSpans);
        $attributes = $span->getAttributes()->toArray();
        $this->assertSame('postgresql', $attributes['db.system']);

        // Cleanup
        if ($result) {
            $this->articlesTable->delete($result);
        }
    }
}
