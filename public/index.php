<?php
declare(strict_types=1);

function public_not_found(): never
{
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
$scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');

$rootRoute = isset($_GET['__root_route']) ? trim((string) $_GET['__root_route']) : null;
if ($rootRoute !== null && $rootRoute !== '') {
    $route = trim($rootRoute, '/');
} elseif ($rootRoute !== null) {
    $route = 'index.php';
} else {
    $uriPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    if ($scriptDir !== '' && str_starts_with($uriPath, $scriptDir . '/')) {
        $uriPath = substr($uriPath, strlen($scriptDir));
    }

    $route = trim($uriPath, '/');
    if ($route === '') {
        $route = 'index.php';
    } elseif (!str_ends_with($route, '.php')) {
        $route .= '/index.php';
    }
}

if ($route === '') {
    $route = 'index.php';
} elseif (!str_ends_with($route, '.php')) {
    $route .= '/index.php';
}

$route = preg_replace('#/+#', '/', $route) ?? '';
$route = ltrim($route, '/');
if (
    $route === ''
    || str_contains($route, '..')
    || preg_match('#(^|/)\.#', $route) === 1
    || preg_match('#^[A-Za-z0-9/_-]+\.php$#', $route) !== 1
) {
    public_not_found();
}

$routeMap = [
    'login.php' => 'auth/login.php',
    'logout.php' => 'auth/logout.php',
    'register.php' => 'auth/register.php',
    'register-otp.php' => 'auth/register-otp.php',
    'forgot-password.php' => 'auth/forgot-password.php',
    'account-security.php' => 'applicant/account-security.php',
    'apply-autosave.php' => 'applicant/apply-autosave.php',
    'apply-photo.php' => 'applicant/apply-photo.php',
    'apply.php' => 'applicant/apply.php',
    'dashboard.php' => 'applicant/dashboard.php',
    'my-application.php' => 'applicant/my-application.php',
    'notifications.php' => 'applicant/notifications.php',
    'preview-document.php' => 'applicant/preview-document.php',
    'print-application-draft.php' => 'applicant/print-application-draft.php',
    'print-application.php' => 'applicant/print-application.php',
    'profile-settings.php' => 'applicant/profile-settings.php',
    'realtime.php' => 'applicant/realtime.php',
    'admin-only/announcements.php' => 'admin/announcements.php',
    'admin-only/application-periods.php' => 'admin/application-periods.php',
    'admin-only/export-reports.php' => 'admin/export-reports.php',
    'admin-only/logs.php' => 'admin/logs.php',
    'admin-only/requirements.php' => 'admin/requirements.php',
    'admin-only/staff.php' => 'admin/staff.php',
];

$internalRoute = $routeMap[$route] ?? $route;

$pagesRoot = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'pages');
if ($pagesRoot === false) {
    public_not_found();
}

$target = realpath($pagesRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $internalRoute));
if ($target === false || !is_file($target) || !str_starts_with(str_replace('\\', '/', $target), str_replace('\\', '/', $pagesRoot) . '/')) {
    public_not_found();
}

$virtualScriptPath = ($scriptDir !== '' ? $scriptDir : '') . '/' . $route;
$virtualScriptPath = preg_replace('#/+#', '/', $virtualScriptPath) ?: '/' . $route;

$_SERVER['PHP_SELF'] = $virtualScriptPath;
$_SERVER['SCRIPT_NAME'] = $virtualScriptPath;
$_SERVER['SCRIPT_FILENAME'] = $target;

require $target;
