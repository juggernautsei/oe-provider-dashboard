<?php

/**
 * Task Manager — team / manager board for open completeness tasks.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

require_once __DIR__ . '/_init.php';

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessTaskService;
use OpenEMR\Common\Acl\AclMain;

if (
    !AclMain::aclCheckCore('admin', 'super')
    && !AclMain::aclCheckCore('acct', 'rep')
    && !AclMain::aclCheckCore('patients', 'demo')
) {
    echo xlt('Not authorized');
    exit;
}

$ctx = provider_dashboard_context();
$assetBase = rtrim($ctx['moduleWebPath'], '/');
$webroot = rtrim($ctx['webroot'], '/');
$csrfToken = provider_dashboard_csrf_token();
$title = 'Team Tasks';

$service = new CompletenessTaskService();
$summary = $service->summarizeOpenByAssignee();
$tasks = $service->listAllOpenTasks(1000);
?>
<!doctype html>
<html>
<?php require_once dirname(__DIR__) . '/resources/header_common.php'; ?>
<style>
    .tm-overdue { color: #b00020; font-weight: 600; }
    .tm-card { border: 1px solid #e5e5e5; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; background: #fff; }
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
        <div class="footer"><p>by Juggernaut Systems Express</p></div>
    </nav>
    <div id="content" class="p-4 p-md-5 pt-5">
        <h2><?php echo xlt('Team board'); ?></h2>
        <p class="text-muted"><?php echo xlt('Open insurance and referral-visit tasks by assignee. Use this to follow up with staff who have overdue work.'); ?></p>

        <div class="row mb-4">
            <?php if ($summary === []) { ?>
                <div class="col-12"><div class="alert alert-success"><?php echo xlt('No open completeness tasks across the team.'); ?></div></div>
            <?php } else {
                foreach ($summary as $row) { ?>
                    <div class="col-md-4">
                        <div class="tm-card">
                            <h5 class="mb-1"><?php echo text($row['name']); ?></h5>
                            <div><?php echo xlt('Open'); ?>: <strong><?php echo text((string) $row['open_count']); ?></strong></div>
                            <div class="<?php echo $row['overdue_count'] > 0 ? 'tm-overdue' : ''; ?>">
                                <?php echo xlt('Overdue'); ?>: <strong><?php echo text((string) $row['overdue_count']); ?></strong>
                            </div>
                        </div>
                    </div>
                <?php }
            } ?>
        </div>

        <h4><?php echo xlt('All open tasks'); ?></h4>
        <table class="table table-sm table-striped">
            <thead class="thead-light">
            <tr>
                <th><?php echo xlt('Due'); ?></th>
                <th><?php echo xlt('Assignee'); ?></th>
                <th><?php echo xlt('Patient'); ?></th>
                <th><?php echo xlt('Task'); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tasks as $task) {
                $dueClass = !empty($task['is_overdue']) ? 'tm-overdue' : '';
                ?>
                <tr>
                    <td class="<?php echo attr($dueClass); ?>">
                        <?php echo !empty($task['due_at']) ? text(oeFormatDateTime($task['due_at'])) : text('—'); ?>
                    </td>
                    <td><?php echo text($task['assignee_name'] ?? ''); ?></td>
                    <td><?php echo text($task['patient_name'] ?? ''); ?> (<?php echo text((string) ($task['pubpid'] ?? $task['pid'])); ?>)</td>
                    <td><?php echo text(xl($task['title'] ?? '')); ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-secondary"
                                onclick="toPatient(<?php echo attr_js((string) $task['pid']); ?>)">
                            <?php echo xlt('Open'); ?>
                        </button>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
        <a href="<?php echo attr($assetBase . '/public/index.php'); ?>" class="btn btn-link">&larr; <?php echo xlt('Back to My Tasks'); ?></a>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/resources/footer_common.php'; ?>
<script>
    function toPatient(pid) {
        top.restoreSession();
        top.RTop.location = <?php echo js_escape($webroot); ?> +
            "/interface/patient_file/summary/demographics.php?set_pid=" + encodeURIComponent(pid);
    }
</script>
</body>
</html>
