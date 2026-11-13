<?php

/**
 * Background service entry for Task Manager completeness rescan.
 *
 * Registered in background_services as TaskManagerCompleteness.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessTaskService;

/**
 * OpenEMR background_services callback.
 */
function task_manager_completeness_scan(): void
{
    $moduleRoot = dirname(__DIR__);
    spl_autoload_register(static function (string $class) use ($moduleRoot): void {
        $prefix = 'Juggernaut\\ProviderDashboard\\Module\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $file = $moduleRoot . '/src/' . $relative . '.php';
        if (is_readable($file)) {
            require_once $file;
        }
    });

    try {
        $service = new CompletenessTaskService();
        $service->syncRecentPatients(30, 1000);
    } catch (Throwable $e) {
        error_log('TaskManagerCompleteness: ' . $e->getMessage());
    }
}
