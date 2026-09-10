<?php

/**
 * AJAX: eSign all unsigned newpatient forms in the dashboard window.
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

require_once __DIR__ . '/_init.php';

use ESign\Form_Factory;
use Juggernaut\ProviderDashboard\Module\Controllers\DashboardData;
use OpenEMR\Common\Auth\AuthUtils;
use OpenEMR\Common\Csrf\CsrfUtils;

header('Content-Type: application/json; charset=utf-8');

const STATUS_SUCCESS = 'success';
const STATUS_FAILURE = 'failure';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => STATUS_FAILURE, 'message' => xlt('Invalid method')]);
    exit;
}

if (!provider_dashboard_verify_csrf($_POST['csrf_token_form'] ?? null)) {
    echo json_encode(['status' => STATUS_FAILURE, 'message' => xlt('Authentication Error')]);
    exit;
}

$ctx = provider_dashboard_context();
$data = new DashboardData($ctx['authUserID']);

if (empty($_POST['user_id'])) {
    echo json_encode(['status' => STATUS_FAILURE, 'message' => xlt('Missing user')]);
    exit;
}

// Ensure caller matches session user
if ((int) $_POST['user_id'] !== $ctx['authUserID']) {
    echo json_encode(['status' => STATUS_FAILURE, 'message' => xlt('User mismatch')]);
    exit;
}

$staff = $data->checkIfSupervisor();
if (count($staff) > 1) {
    $res = $data->encounterMultiProviderForSignData($staff);
} else {
    $res = $data->encounterSingleProviderForSignData();
}

$password = (string) ($_POST['password'] ?? '');
$amendment = (string) ($_POST['amendment'] ?? '');

if ($password === '') {
    echo json_encode(['status' => STATUS_FAILURE, 'message' => xlt('Please enter password first')]);
    exit;
}

$valid = (new AuthUtils())->confirmPassword($ctx['authUser'], $password);
if (!$valid) {
    echo json_encode(['status' => STATUS_FAILURE, 'message' => xlt('The password you entered is invalid')]);
    exit;
}

$srcdir = $ctx['srcdir'];
$factoryFile = rtrim($srcdir, '/') . '/ESign/Form/Factory.php';
if (is_readable($factoryFile)) {
    require_once $factoryFile;
}

if ($res) {
    while ($row = sqlFetchArray($res)) {
        $signData = $data->fetchEsignRecord($row['id']);
        if (empty($signData)) {
            $factory = new Form_Factory($row['id'], $row['formdir'], $row['encounter']);
            $signable = $factory->createSignable();
            $signable->sign($ctx['authUserID'], true, $amendment);
        }
    }
}

echo json_encode(['status' => STATUS_SUCCESS, 'message' => xlt('Form signed successfully')]);
