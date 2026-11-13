<?php

/**
 * Task Manager — chart completeness tasks (insurance plans + referral visits).
 *
 * @package OpenEMR
 * @author  Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

require_once __DIR__ . '/_init.php';

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessTaskService;
use OpenEMR\Common\Acl\AclMain;

$ctx = provider_dashboard_context();
$assetBase = rtrim($ctx['moduleWebPath'], '/');
$webroot = rtrim($ctx['webroot'], '/');
$userId = (int) $ctx['authUserID'];
$csrfToken = provider_dashboard_csrf_token();
$title = 'Task Manager';

$service = new CompletenessTaskService();

// Optional rescan of current session user workload when opening the page
if (!empty($_POST['rescan']) && provider_dashboard_verify_csrf($_POST['token'] ?? null)) {
    $service->syncRecentPatients(14, 200);
}

$tasks = $service->listOpenTasksForUser($userId, true);
$openCount = count($tasks);
$isManager = AclMain::aclCheckCore('admin', 'super')
    || AclMain::aclCheckCore('acct', 'rep')
    || AclMain::aclCheckCore('patients', 'demo');
?>
<!doctype html>
<html>
<?php require_once dirname(__DIR__) . '/resources/header_common.php'; ?>
<meta name="csrf-token" content="<?php echo attr($csrfToken); ?>">
<style>
    .tm-overdue { color: #b00020; font-weight: 600; }
    .tm-badge { display: inline-block; min-width: 1.5rem; padding: 0.15rem 0.45rem; border-radius: 999px; background: #866ec7; color: #fff; font-size: 0.8rem; }
    .tm-rule { font-family: monospace; font-size: 0.75rem; color: #666; }
</style>
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
            <p>by Juggernaut Systems Express</p>
        </div>
    </nav>

    <div id="content" class="p-4 p-md-5 pt-5">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <div>
                <h2 class="mb-1">
                    <?php echo xlt('My Tasks'); ?>
                    <span class="tm-badge"><?php echo text((string) $openCount); ?></span>
                </h2>
                <p class="mb-0 text-muted">
                    <?php echo xlt('Missing insurance plan details and referral/prior-auth visit limits assigned to you (and your staff if you supervise them).'); ?>
                </p>
            </div>
            <div class="d-flex gap-2 mt-2">
                <form method="post" class="mb-0" onsubmit="top.restoreSession();">
                    <input type="hidden" name="token" value="<?php echo attr($csrfToken); ?>">
                    <button type="submit" name="rescan" value="1" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-refresh"></i> <?php echo xlt('Rescan'); ?>
                    </button>
                </form>
                <?php if ($isManager) { ?>
                    <a class="btn btn-sm btn-primary" href="<?php echo attr($assetBase . '/public/team.php'); ?>">
                        <i class="fa fa-users"></i> <?php echo xlt('Team board'); ?>
                    </a>
                <?php } ?>
            </div>
        </div>

        <?php if (!$service->tableExists()) { ?>
            <div class="alert alert-warning">
                <?php echo xlt('Completeness task tables are not installed yet. Re-run Module Manager Install/Upgrade for Task Manager.'); ?>
            </div>
        <?php } ?>

        <table class="table table-striped table-hover" id="completeness-tasks">
            <thead class="thead-light">
            <tr>
                <th><?php echo xlt('Due'); ?></th>
                <th><?php echo xlt('Patient'); ?></th>
                <th><?php echo xlt('Task'); ?></th>
                <th><?php echo xlt('Assignee'); ?></th>
                <th><?php echo xlt('Action'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($tasks === []) { ?>
                <tr>
                    <td colspan="5"><?php echo xlt('No open chart completeness tasks. Nice work.'); ?></td>
                </tr>
            <?php } else {
                foreach ($tasks as $task) {
                    $dueLabel = !empty($task['due_at']) ? text(oeFormatDateTime($task['due_at'])) : text(xl('—'));
                    $dueClass = !empty($task['is_overdue']) ? 'tm-overdue' : '';
                    $fixUrl = $webroot . '/' . ltrim((string) ($task['fix_path_resolved'] ?? ''), '/');
                    // Ensure patient context for demographics-style links
                    if (str_contains($fixUrl, 'demographics.php') && !str_contains($fixUrl, 'set_pid=')) {
                        $fixUrl .= (str_contains($fixUrl, '?') ? '&' : '?') . 'set_pid=' . urlencode((string) $task['pid']);
                    }
                    if (str_contains($fixUrl, 'patient_auth_manager.php')) {
                        // Prior auth manager uses session pid; open demographics first with set_pid then user navigates, or set_pid query if supported
                        $fixUrl = $webroot . '/interface/patient_file/summary/demographics.php?set_pid=' . urlencode((string) $task['pid']);
                    }
                    ?>
                    <tr>
                        <td class="<?php echo attr($dueClass); ?>"><?php echo $dueLabel; ?></td>
                        <td>
                            <strong><?php echo text($task['patient_name'] ?? ''); ?></strong><br>
                            <small><?php echo text($task['pubpid'] ?? $task['pid'] ?? ''); ?></small>
                        </td>
                        <td>
                            <div><?php echo text(xl($task['title'] ?? '')); ?></div>
                            <div class="text-muted small"><?php echo text(xl($task['description'] ?? '')); ?></div>
                            <div class="tm-rule"><?php echo text($task['rule_code'] ?? ''); ?></div>
                        </td>
                        <td><?php echo text($task['assignee_name'] ?? ''); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-secondary"
                                    onclick="toPatient(<?php echo attr_js((string) $task['pid']); ?>, <?php echo attr_js($fixUrl); ?>)">
                                <?php echo xlt('Open chart'); ?>
                            </button>
                        </td>
                    </tr>
                    <?php
                }
            } ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/resources/footer_common.php'; ?>
<script>
    function toPatient(pid, url) {
        top.restoreSession();
        // Prefer loading patient chart with pid so insurance/auth screens have context
        top.RTop.location = <?php echo js_escape($webroot); ?> +
            "/interface/patient_file/summary/demographics.php?set_pid=" + encodeURIComponent(pid);
    }
</script>
</body>
</html>
