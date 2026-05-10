<?php
declare(strict_types=1);

namespace OtelInstrumentation\Test\TestCase\Integration;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use OtelInstrumentation\Instrumentation\ExclusionRegistry;
use OtelInstrumentation\Test\TestCase\OtelTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that Configure-driven exclusion suppresses Controller spans
 * and cascades to Table spans executed under an excluded action.
 */
class ExcludeIntegrationTest extends TestCase
{
    use OtelTestTrait;

    private static bool $instrumentationLoaded = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!self::$instrumentationLoaded) {
            require_once dirname(__DIR__, 3) . '/src/Instrumentation/ControllerInstrumentation.php';
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

        Configure::write('App.encoding', 'UTF-8');
        ExclusionRegistry::reset();
        $this->setUpOtel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        TableRegistry::getTableLocator()->clear();
        ExclusionRegistry::reset();
        $this->resetOtel();
    }

    private function makeController(string $action): Controller
    {
        $request = new ServerRequest([
            'url' => '/exclude-test/' . $action,
            'environment' => [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/exclude-test/' . $action,
            ],
        ]);
        $request = $request->withParam('action', $action);

        $controller = new class ($request) extends Controller {
        };
        $controller->disableAutoRender();

        return $controller;
    }

    public function testExcludedActionDoesNotCreateControllerSpan(): void
    {
        $controller = $this->makeController('ping');
        ExclusionRegistry::register([
            ['controller' => get_class($controller), 'action' => '*'],
        ]);

        $controller->invokeAction(fn () => null, []);

        $this->assertCount(0, $this->getSpans());
    }

    public function testExcludedSpecificActionWhileOthersStillTraced(): void
    {
        $pingController = $this->makeController('ping');
        $indexController = $this->makeController('index');

        ExclusionRegistry::register([
            ['controller' => get_class($pingController), 'action' => 'ping'],
        ]);

        $pingController->invokeAction(fn () => null, []);
        $this->assertCount(0, $this->getSpans(), 'ping should be excluded');

        $indexController->invokeAction(fn () => null, []);
        $spans = $this->getSpans();
        $this->assertCount(1, $spans, 'index should still produce a span');
        $this->assertStringContainsString('::index', $spans[0]->getName());
    }

    public function testExcludedControllerSuppressesTableSpans(): void
    {
        $articlesTable = TableRegistry::getTableLocator()->get('OtelTestArticles', [
            'className' => Table::class,
        ]);

        $controller = $this->makeController('ping');
        ExclusionRegistry::register([
            ['controller' => get_class($controller), 'action' => '*'],
        ]);

        $controller->invokeAction(function () use ($articlesTable) {
            $articlesTable->find('all')->all();
        }, []);

        $this->assertCount(0, $this->getSpans(), 'Both controller and table spans should be suppressed');
    }

    public function testNonExcludedControllerStillProducesTableSpans(): void
    {
        $articlesTable = TableRegistry::getTableLocator()->get('OtelTestArticles', [
            'className' => Table::class,
        ]);

        $controller = $this->makeController('index');

        $controller->invokeAction(function () use ($articlesTable) {
            $articlesTable->find('all')->all();
        }, []);

        $spans = $this->getSpans();
        $names = array_map(fn ($s) => $s->getName(), $spans);
        $hasController = false;
        $hasTable = false;
        foreach ($spans as $span) {
            if (str_contains($span->getName(), '::index')) {
                $hasController = true;
            }
            if (str_contains($span->getName(), 'OtelTestArticles.find')) {
                $hasTable = true;
            }
        }
        $this->assertTrue($hasController, 'Controller span should exist. Got: ' . implode(', ', $names));
        $this->assertTrue($hasTable, 'Table span should exist. Got: ' . implode(', ', $names));
    }

    public function testExceptionInExcludedActionDoesNotLeakDepth(): void
    {
        $controller = $this->makeController('ping');
        ExclusionRegistry::register([
            ['controller' => get_class($controller), 'action' => '*'],
        ]);

        try {
            $controller->invokeAction(function () {
                throw new \RuntimeException('boom');
            }, []);
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertFalse(
            ExclusionRegistry::isCurrentlyExcluded(),
            'depth must return to 0 even when the action threw'
        );
    }

    public function testEmptyExcludeConfigBehavesAsBefore(): void
    {
        $controller = $this->makeController('index');

        $controller->invokeAction(fn () => null, []);

        $spans = $this->getSpans();
        $this->assertCount(1, $spans);
    }
}
