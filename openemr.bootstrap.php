<?php

/**
 * OpenEMR module bootstrap for Provider Dashboard.
 *
 * @package OpenEMR
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

use Juggernaut\ProviderDashboard\Module\Bootstrap;
use OpenEMR\Core\OEGlobalsBag;

/**
 * @global OpenEMR\Core\ModulesClassLoader $classLoader
 */
$classLoader->registerNamespaceIfNotExists(
    'Juggernaut\\ProviderDashboard\\Module\\',
    __DIR__ . DIRECTORY_SEPARATOR . 'src'
);

/**
 * @global Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
 */
$kernel = null;
if (class_exists(OEGlobalsBag::class)) {
    $kernel = OEGlobalsBag::getInstance()->get('kernel');
}
if ($kernel === null && isset($GLOBALS['kernel'])) {
    $kernel = $GLOBALS['kernel'];
}

$bootstrap = new Bootstrap($eventDispatcher, $kernel);
$bootstrap->subscribeToEvents();
