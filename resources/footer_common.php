<?php

/**
 * Shared footer scripts for Provider Dashboard.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

$ctx = provider_dashboard_context();
$assetBase = rtrim($ctx['moduleWebPath'], '/');
?>
<script src="<?php echo attr($assetBase . '/js/popper.js'); ?>"></script>
<script src="<?php echo attr($assetBase . '/js/bootstrap.min.js'); ?>"></script>
<script src="<?php echo attr($assetBase . '/js/main.js'); ?>"></script>
