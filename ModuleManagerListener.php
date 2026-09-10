<?php

/**
 * Module Manager lifecycle hooks for Provider Dashboard.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

use OpenEMR\Core\AbstractModuleActionListener;

class ModuleManagerListener extends AbstractModuleActionListener
{
    public function __construct()
    {
        parent::__construct();
    }

    public function moduleManagerAction($methodName, $modId, string $currentActionStatus = 'Success'): string
    {
        if (method_exists(self::class, $methodName)) {
            return self::$methodName($modId, $currentActionStatus);
        }
        return $currentActionStatus;
    }

    public static function getModuleNamespace(): string
    {
        return 'Juggernaut\\ProviderDashboard\\Module\\';
    }

    public static function initListenerSelf(): ModuleManagerListener
    {
        return new self();
    }

    private function install($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    private function enable($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    private function disable($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    private function unregister($modId, $currentActionStatus): mixed
    {
        try {
            sqlStatement(
                "DELETE FROM `list_options` WHERE `list_id` = 'default_open_tabs' AND `option_id` = 'pdb'"
            );
        } catch (Throwable $e) {
            error_log('oe-provider-dashboard unregister: ' . $e->getMessage());
        }
        return $currentActionStatus;
    }

    private function install_sql($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }

    private function upgrade_sql($modId, $currentActionStatus): mixed
    {
        return $currentActionStatus;
    }
}
