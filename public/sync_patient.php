<?php

/**
 * AJAX/manual completeness resync for one patient.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

require_once __DIR__ . '/_init.php';

use Juggernaut\ProviderDashboard\Module\Service\Completeness\CompletenessTaskService;

header('Content-Type: application/json');

$token = $_POST['token'] ?? $_GET['token'] ?? null;
if (!provider_dashboard_verify_csrf(is_string($token) ? $token : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

$pid = (int) ($_POST['pid'] ?? $_GET['pid'] ?? 0);
if ($pid <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'pid']);
    exit;
}

$ctx = provider_dashboard_context();
$userId = (int) $ctx['authUserID'];
$service = new CompletenessTaskService();
$result = $service->syncPatient($pid, $userId > 0 ? $userId : null, $userId > 0 ? $userId : null);

echo json_encode(['ok' => true, 'result' => $result]);
