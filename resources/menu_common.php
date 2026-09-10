<?php

/**
 * Sidebar menu for Provider Dashboard.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

$ctx = provider_dashboard_context();
$base = rtrim($ctx['moduleWebPath'], '/') . '/public';
$webroot = $ctx['webroot'];
?>
<div class="p-4 pt-5">
    <h1><a href="<?php echo attr($base . '/index.php'); ?>" class="logo"><?php echo xlt('Tasks'); ?></a></h1>
    <ul class="list-unstyled components mb-5">
        <li>
            <a href="<?php echo attr($base . '/index.php'); ?>"><?php echo xlt('Missing Documentation'); ?></a>
        </li>
        <li>
            <a href="<?php echo attr($base . '/cosign.php'); ?>"><?php echo xlt('Cosign Notes'); ?></a>
        </li>
        <li>
            <a href="#" onclick="top.restoreSession(); top.left_nav.loadFrame('1', 'pmc', '<?php echo attr($webroot); ?>/portal/messaging/messages.php'); return false;"><?php echo xlt('Portal Messaging'); ?></a>
        </li>
    </ul>
