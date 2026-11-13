<?php

/**
 * Shared bootstrap for Task Manager public pages (OpenEMR 8.x friendly).
 *
 * Access is staff-wide: providers, front desk, billing, clinical, admin.
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

// ACL: any common staff role that works patient charts or billing
if (
    !AclMain::aclCheckCore('encounters', 'coding_a')
    && !AclMain::aclCheckCore('encounters', 'auth_a')
    && !AclMain::aclCheckCore('encounters', 'notes')
    && !AclMain::aclCheckCore('patients', 'med')
    && !AclMain::aclCheckCore('patients', 'demo')
    && !AclMain::aclCheckCore('patients', 'appt')
    && !AclMain::aclCheckCore('acct', 'bill')
    && !AclMain::aclCheckCore('admin', 'super')
) {
    echo (new TwigContainer(null, $GLOBALS['kernel'] ?? null))->getTwig()->render(
        'core/unauthorized.html.twig',
        ['pageTitle' => xl('Task Manager')]
    );
    exit;
}

// Prefer global bag when available
$oeGlobals = class_exists(OEGlobalsBag::class) ? OEGlobalsBag::getInstance() : null;
$webroot = $oeGlobals ? (string) $oeGlobals->get('webroot', $GLOBALS['webroot'] ?? '') : (string) ($GLOBALS['webroot'] ?? '');
$srcdir = $oeGlobals ? (string) $oeGlobals->get('srcdir', $GLOBALS['srcdir'] ?? '') : (string) ($GLOBALS['srcdir'] ?? '');
$fileroot = $oeGlobals ? (string) $oeGlobals->get('fileroot', $GLOBALS['fileroot'] ?? '') : (string) ($GLOBALS['fileroot'] ?? '');

// Session wrapper (OE 8.x). Do not swallow failures from getActiveSession —
// CSRF and auth both depend on a real session object when available.
$authUserID = 0;
$authUser = '';
$session = null;
if (class_exists(SessionWrapperFactory::class)) {
    $factory = SessionWrapperFactory::getInstance();
    if (method_exists($factory, 'getActiveSession')) {
        // OpenEMR 8.x+ (Symfony SessionInterface)
        $session = $factory->getActiveSession();
    }
    // Some builds only populate active session after getCoreSession()
    if ($session === null && method_exists($factory, 'getCoreSession')) {
        $session = $factory->getCoreSession();
    }
    if ($session === null && method_exists($factory, 'getWrapper')) {
        // Older wrapper API
        $session = $factory->getWrapper();
    }
}

if (is_object($session) && method_exists($session, 'get')) {
    $authUserID = (int) $session->get('authUserID', 0);
    $authUser = (string) $session->get('authUser', '');
} elseif (is_array($session) || $session instanceof ArrayAccess) {
    $authUserID = (int) ($session['authUserID'] ?? 0);
    $authUser = (string) ($session['authUser'] ?? '');
}

// Legacy $_SESSION fallback (still populated by globals.php on many installs)
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
 * Resolve the active OpenEMR session object when available.
 */
function provider_dashboard_session(): mixed
{
    $ctx = provider_dashboard_context();
    $session = $ctx['session'] ?? null;
    if ($session !== null) {
        return $session;
    }

    if (!class_exists(SessionWrapperFactory::class)) {
        return null;
    }

    $factory = SessionWrapperFactory::getInstance();
    if (method_exists($factory, 'getActiveSession')) {
        $active = $factory->getActiveSession();
        if ($active !== null) {
            return $active;
        }
    }
    if (method_exists($factory, 'getCoreSession')) {
        return $factory->getCoreSession();
    }
    if (method_exists($factory, 'getWrapper')) {
        return $factory->getWrapper();
    }

    return null;
}

/**
 * Whether CsrfUtils::collectCsrfToken expects a session object as the first argument.
 */
function provider_dashboard_csrf_needs_session(): bool
{
    static $needsSession = null;
    if ($needsSession !== null) {
        return $needsSession;
    }

    $needsSession = false;
    try {
        $ref = new ReflectionMethod(CsrfUtils::class, 'collectCsrfToken');
        $params = $ref->getParameters();
        if ($params === []) {
            return false;
        }
        $first = $params[0];
        if ($first->getName() === 'session') {
            $needsSession = true;
            return true;
        }
        $type = $first->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $needsSession = true;
            return true;
        }
    } catch (Throwable $e) {
        $needsSession = false;
    }

    return $needsSession;
}

/**
 * Collect CSRF token — supports both OE 7.x ($_SESSION) and OE 8.x (SessionInterface) APIs.
 */
function provider_dashboard_csrf_token(string $subject = 'default'): string
{
    if (provider_dashboard_csrf_needs_session()) {
        $session = provider_dashboard_session();
        if ($session === null) {
            throw new RuntimeException(
                'No active session available for CSRF token. ' .
                'Ensure globals.php started the core session before Task Manager loaded.'
            );
        }
        // Named argument matches OE 8.x prior-auth / core call sites.
        return (string) CsrfUtils::collectCsrfToken(session: $session, subject: $subject);
    }

    $token = CsrfUtils::collectCsrfToken($subject);
    if ($token === false || $token === null || $token === '') {
        throw new RuntimeException('Unable to collect CSRF token (legacy session key missing).');
    }

    return (string) $token;
}

/**
 * Verify CSRF token — dual API support like collect.
 */
function provider_dashboard_verify_csrf(?string $token, string $subject = 'default'): bool
{
    if ($token === null || $token === '') {
        return false;
    }

    if (provider_dashboard_csrf_needs_session()) {
        $session = provider_dashboard_session();
        if ($session === null) {
            return false;
        }
        return CsrfUtils::verifyCsrfToken($token, session: $session, subject: $subject);
    }

    return (bool) CsrfUtils::verifyCsrfToken($token, $subject);
}

