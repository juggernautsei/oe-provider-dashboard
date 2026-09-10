<?php

/**
 * Cosign notes placeholder page.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

require_once __DIR__ . '/_init.php';

$title = xlt('Cosign Notes');
$ctx = provider_dashboard_context();
?>
<!doctype html>
<html>
<?php require_once dirname(__DIR__) . '/resources/header_common.php'; ?>
<body>
<div class="wrapper d-flex align-items-stretch">
    <nav id="sidebar">
        <div class="custom-menu">
            <button type="button" id="sidebarCollapse" class="btn btn-primary">
                <i class="fa fa-bars"></i>
                <span class="sr-only">Toggle Menu</span>
            </button>
        </div>
        <?php require_once dirname(__DIR__) . '/resources/menu_common.php'; ?>
        <div class="footer">
            <p><?php echo xlt('by Juggernaut Systems Express'); ?></p>
        </div>
    </nav>
    <div id="content" class="p-4 p-md-5 pt-5">
        <h2 class="mb-4"><?php echo xlt('Notes'); ?></h2>
        <p class="text-muted"><?php echo xlt('Cosign workflow placeholder — extend in a later iteration.'); ?></p>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/resources/footer_common.php'; ?>
</body>
</html>
