<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Integration;

use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OtelInstrumentation\Test\TestCase\OtelTestTrait;
use PHPUnit\Framework\TestCase;

class TableInstrumentationTest extends TestCase
{
    use OtelTestTrait;

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

        if (!extension_loaded('opentelemetry')) {
            $this->markTestSkipped('ext-opentelemetry is not installed.');
        }

        $this->setUpOtel();

        $this->articlesTable = TableRegistry::getTableLocator()->get('OtelTestArticles', [
            'className' => Table::class,
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        TableRegistry::getTableLocator()->clear();
        $this->resetOtel();
    }

    public function testFindCreatesSpanWithCorrectAttributes(): void
    {
        $this->articlesTable->find('all');

        $spans = $this->getSpans();
        $this->assertGreaterThanOrEqual(1, count($spans));

        $span = $spans[count($spans) - 1];
        $this->assertSame('OtelTestArticles.find(all)', $span->getName());
        $this->assertSame(SpanKind::KIND_CLIENT, $span->getKind());
        $this->assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());

        $this->assertSame('postgresql', $this->getSpanAttribute($span, 'db.system'));
        $this->assertSame('OtelTestArticles', $this->getSpanAttribute($span, 'cake.table'));
        $this->assertSame('all', $this->getSpanAttribute($span, 'cake.find_type'));
    }

    public function testFindListCreatesSpanWithListType(): void
    {
        $this->articlesTable->find('list');

        $spans = $this->getSpans();
        $this->assertGreaterThanOrEqual(1, count($spans));

        $span = $spans[count($spans) - 1];
        $this->assertSame('OtelTestArticles.find(list)', $span->getName());
        $this->assertSame('list', $this->getSpanAttribute($span, 'cake.find_type'));
    }

    public function testSaveNewEntityCreatesSpan(): void
    {
        $entity = $this->articlesTable->newEntity([
            'title' => 'Test Article',
            'body' => 'Test body',
        ]);

        $result = $this->articlesTable->save($entity);

        $spans = $this->getSpans();
        $saveSpans = array_values(array_filter($spans, fn ($s) => str_contains($s->getName(), '.save')));
        $this->assertNotEmpty($saveSpans);

        $span = end($saveSpans);
        $this->assertSame('OtelTestArticles.save', $span->getName());
        $this->assertSame(SpanKind::KIND_CLIENT, $span->getKind());
        $this->assertSame(StatusCode::STATUS_OK, $span->getStatus()->getCode());

        $this->assertSame('postgresql', $this->getSpanAttribute($span, 'db.system'));
        $this->assertSame('OtelTestArticles', $this->getSpanAttribute($span, 'cake.table'));
        $this->assertTrue($this->getSpanAttribute($span, 'cake.entity.new'));

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

        // Reset to only capture delete span
        $this->setUpOtel();

        $this->articlesTable->delete($saved);

        $spans = $this->getSpans();
        $deleteSpans = array_values(array_filter($spans, fn ($s) => str_contains($s->getName(), '.delete')));
        $this->assertNotEmpty($deleteSpans);

        $span = end($deleteSpans);
        $this->assertSame('OtelTestArticles.delete', $span->getName());
        $this->assertSame(SpanKind::KIND_CLIENT, $span->getKind());
        $this->assertSame('OtelTestArticles', $this->getSpanAttribute($span, 'cake.table'));
    }

    public function testSaveWithDbSystemAttribute(): void
    {
        $entity = $this->articlesTable->newEntity([
            'title' => 'DB System Test',
            'body' => 'Testing db.system attribute',
        ]);

        $result = $this->articlesTable->save($entity);

        $spans = $this->getSpans();
        $saveSpans = array_values(array_filter($spans, fn ($s) => str_contains($s->getName(), '.save')));
        $this->assertNotEmpty($saveSpans);

        $span = end($saveSpans);
        $this->assertSame('postgresql', $this->getSpanAttribute($span, 'db.system'));

        // Cleanup
        if ($result) {
            $this->articlesTable->delete($result);
        }
    }
}
