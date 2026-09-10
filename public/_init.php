<?php

/**
 * Shared bootstrap for Provider Dashboard public pages (OpenEMR 8.x friendly).
 *
 * @package OpenEMR
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU GPL-3.0-only
 */

declare(strict_types=1);

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\OEGlobalsBag;

// interface/globals.php from .../custom_modules/oe-provider-dashboard/public
require_once dirname(__DIR__, 4) . '/globals.php';

// ACL: encounters coding (adjust per site if needed)
// Allow common clinical/billing roles that need the documentation dashboard
if (
    !AclMain::aclCheckCore('encounters', 'coding_a')
    && !AclMain::aclCheckCore('encounters', 'auth_a')
    && !AclMain::aclCheckCore('encounters', 'notes')
    && !AclMain::aclCheckCore('patients', 'med')
    && !AclMain::aclCheckCore('admin', 'super')
) {
    echo (new TwigContainer(null, $GLOBALS['kernel'] ?? null))->getTwig()->render(
        'core/unauthorized.html.twig',
        ['pageTitle' => xl('Provider Dashboard')]
    );
    exit;
}

// Prefer global bag when available
$oeGlobals = class_exists(OEGlobalsBag::class) ? OEGlobalsBag::getInstance() : null;
$webroot = $oeGlobals ? (string) $oeGlobals->get('webroot', $GLOBALS['webroot'] ?? '') : (string) ($GLOBALS['webroot'] ?? '');
$srcdir = $oeGlobals ? (string) $oeGlobals->get('srcdir', $GLOBALS['srcdir'] ?? '') : (string) ($GLOBALS['srcdir'] ?? '');
$fileroot = $oeGlobals ? (string) $oeGlobals->get('fileroot', $GLOBALS['fileroot'] ?? '') : (string) ($GLOBALS['fileroot'] ?? '');

// Session wrapper (8.x); fall back to $_SESSION for transitional installs
$authUserID = 0;
$authUser = '';
$session = null;
try {
    if (class_exists(SessionWrapperFactory::class)) {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
        if (is_object($session) && method_exists($session, 'get')) {
            $authUserID = (int) $session->get('authUserID', 0);
            $authUser = (string) $session->get('authUser', '');
        } elseif (is_array($session) || $session instanceof ArrayAccess) {
            $authUserID = (int) ($session['authUserID'] ?? 0);
            $authUser = (string) ($session['authUser'] ?? '');
        }
    }
} catch (Throwable $e) {
    // fall through
}
if ($authUserID === 0 && isset($_SESSION['authUserID'])) {
    $authUserID = (int) $_SESSION['authUserID'];
}
if ($authUser === '' && isset($_SESSION['authUser'])) {
    $authUser = (string) $_SESSION['authUser'];
}

// Module paths
$moduleRoot = dirname(__DIR__);
$moduleWebPath = rtrim($webroot, '/') . '/interface/modules/custom_modules/oe-provider-dashboard';

// Autoload module controllers
spl_autoload_register(static function (string $class) use ($moduleRoot): void {
    $prefix = 'Juggernaut\\ProviderDashboard\\Module\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $moduleRoot . '/src/' . $relative . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});

// Core library helpers used by the dashboard
$formsInc = ($fileroot !== '' ? $fileroot : dirname(__DIR__, 5)) . '/library/forms.inc.php';
if (is_readable($formsInc)) {
    require_once $formsInc;
}

/**
 * @return array{authUserID:int,authUser:string,webroot:string,srcdir:string,moduleWebPath:string,moduleRoot:string}
 */
function provider_dashboard_context(): array
{
    global $authUserID, $authUser, $webroot, $srcdir, $moduleWebPath, $moduleRoot, $session;
    return [
        'authUserID' => $authUserID,
        'authUser' => $authUser,
        'webroot' => $webroot,
        'srcdir' => $srcdir,
        'moduleWebPath' => $moduleWebPath,
        'moduleRoot' => $moduleRoot,
        'session' => $session,
    ];
}

/**
 * Collect CSRF token using OE 8.x session-aware API.
 */
function provider_dashboard_csrf_token(string $subject = 'default'): string
{
    $ctx = provider_dashboard_context();
    $session = $ctx['session'] ?? null;
    if ($session === null) {
        // Last resort: try factory again
        if (class_exists(SessionWrapperFactory::class)) {
            $session = SessionWrapperFactory::getInstance()->getActiveSession();
        }
    }
    if ($session === null) {
        throw new RuntimeException('No active session available for CSRF token');
    }
    return CsrfUtils::collectCsrfToken($session, $subject);
}

/**
 * Verify CSRF token using OE 8.x session-aware API.
 */
function provider_dashboard_verify_csrf(?string $token, string $subject = 'default'): bool
{
    $ctx = provider_dashboard_context();
    $session = $ctx['session'] ?? null;
    if ($session === null && class_exists(SessionWrapperFactory::class)) {
        $session = SessionWrapperFactory::getInstance()->getActiveSession();
    }
    if ($session === null) {
        return false;
    }
    return CsrfUtils::verifyCsrfToken($token, $session, $subject);
}

