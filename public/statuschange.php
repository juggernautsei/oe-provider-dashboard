<?php

/**
 * AJAX: toggle review status on a newpatient form row.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

require_once __DIR__ . '/_init.php';

use Juggernaut\ProviderDashboard\Module\Controllers\DashboardData;
use OpenEMR\Common\Csrf\CsrfUtils;

header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo xlt('Invalid method');
    exit;
}

if (!provider_dashboard_verify_csrf($_POST['csrf_token_form'] ?? null)) {
    http_response_code(403);
    echo xlt('Authentication Error');
    exit;
}

if (empty($_POST['encounter'])) {
    echo xlt('Missing encounter');
    exit;
}

$ctx = provider_dashboard_context();
$changeStatus = new DashboardData($ctx['authUserID']);
if (isset($_POST['status'])) {
    $newStatus = ((string) $_POST['status'] === '1') ? null : 1;
    $changeStatus->updateReviewStatus($_POST['encounter'], $newStatus, $_POST['id'] ?? 0);
    echo xlt('success') . ' ' . text((string) $_POST['encounter']);
}
