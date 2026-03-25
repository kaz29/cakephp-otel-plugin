<?php
declare(strict_types=1);

namespace OtelInstrumentation;

use Cake\Core\BasePlugin;
use Cake\Core\PluginApplicationInterface;

class Plugin extends BasePlugin
{
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);
        require_once __DIR__ . '/Instrumentation/ControllerInstrumentation.php';
        require_once __DIR__ . '/Instrumentation/TableInstrumentation.php';
    }
}
