<?php
declare(strict_types=1);

namespace OtelInstrumentation;

use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use OtelInstrumentation\Instrumentation\CustomInstrumentation;
use OtelInstrumentation\Instrumentation\ExclusionRegistry;

class Plugin extends BasePlugin
{
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
        require_once __DIR__ . '/Instrumentation/ControllerInstrumentation.php';
        require_once __DIR__ . '/Instrumentation/TableInstrumentation.php';

        $exclude = Configure::read('OtelInstrumentation.exclude');
        if (is_array($exclude) && !empty($exclude)) {
            ExclusionRegistry::register($exclude);
        }

        $hooks = Configure::read('OtelInstrumentation.hooks');
        if (is_array($hooks) && !empty($hooks)) {
            CustomInstrumentation::loadFromConfig($hooks);
        }

        CustomInstrumentation::apply();
    }
}
