<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$secureCookie = App\Support\Env::bool('SESSION_SECURE_COOKIE')
    || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name('garwinai_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self' https://api.open-meteo.com https://geocoding-api.open-meteo.com; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'");

set_exception_handler(static function (Throwable $exception): void {
    error_log((string) $exception);
    http_response_code(500);
    if (App\Support\Env::bool('APP_DEBUG')) {
        echo '<pre>' . e($exception->getMessage()) . '</pre>';
        return;
    }

    echo 'Something went wrong. Please try again later.';
});

$auth = new App\Http\Controllers\AuthController();
$dashboard = new App\Http\Controllers\DashboardController();
$appZone = new App\Http\Controllers\AppZoneController();
$settings = new App\Http\Controllers\SettingsController();
$storage = new App\Http\Controllers\StorageController();
$calendar = new App\Http\Controllers\CalendarController();
$assistant = new App\Http\Controllers\AssistantController();
$wordbase = new App\Http\Controllers\WordbaseController();
$developer = new App\Http\Controllers\DeveloperController();
$home = new App\Http\Controllers\HomeController();
$admin = new App\Http\Controllers\AdminController();
$billing = new App\Http\Controllers\BillingController();
$messages = new App\Http\Controllers\MessagesController();
$router = new App\Http\Router();
$router->get('/', [$home, 'index']);
$router->get('/register', [$auth, 'showRegister']);
$router->post('/register', [$auth, 'register']);
$router->get('/login', [$auth, 'showLogin']);
$router->post('/login', [$auth, 'login']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/billing', [$billing, 'index']);
$router->post('/billing/checkout', [$billing, 'checkout']);
$router->post('/billing/portal', [$billing, 'portal']);
$router->post('/webhooks/stripe', [$billing, 'webhook']);
$router->get('/dashboard', [$dashboard, 'index']);
$router->post('/dashboard/layout', [$dashboard, 'saveLayout']);
$router->get('/settings', [$settings, 'index']);
$router->post('/settings', [$settings, 'save']);
$router->get('/storage', [$storage, 'index']);
$router->post('/storage/folders', [$storage, 'createFolder']);
$router->post('/storage/folders/delete', [$storage, 'deleteFolder']);
$router->post('/storage/upload', [$storage, 'upload']);
$router->get('/storage/download', [$storage, 'download']);
$router->post('/storage/delete', [$storage, 'deleteFile']);
$router->get('/messages', [$messages, 'index']);
$router->get('/calendar', [$calendar, 'index']);
$router->post('/calendar/events', [$calendar, 'save']);
$router->post('/calendar/events/delete', [$calendar, 'delete']);
$router->get('/assistant', [$assistant, 'index']);
$router->post('/assistant/send', [$assistant, 'send']);
$router->get('/assistant/messages', [$assistant, 'messages']);
$router->post('/assistant/rename', [$assistant, 'rename']);
$router->post('/assistant/delete', [$assistant, 'delete']);
$router->get('/developer', [$developer, 'index']);
$router->get('/wordbase', [$wordbase, 'index']);
$router->post('/wordbase/save', [$wordbase, 'save']);
$router->post('/wordbase/delete', [$wordbase, 'delete']);
$router->get('/appzone', [$appZone, 'index']);
$router->get('/appzone/open', [$appZone, 'open']);
$router->post('/appzone/rate', [$appZone, 'rate']);
$router->post('/appzone/install', [$appZone, 'install']);
$router->post('/appzone/purchase', [$appZone, 'purchase']);
$router->get('/admin/appzone', [$appZone, 'admin']);
$router->post('/admin/appzone/create', [$appZone, 'create']);
$router->post('/admin/appzone/publish', [$appZone, 'togglePublished']);
$router->get('/admin', [$admin, 'index']);
$router->get('/admin/settings', [$admin, 'settings']);
$router->post('/admin/integrations/save', [$admin, 'saveIntegration']);
$router->post('/admin/integrations/test', [$admin, 'testIntegration']);
$router->post('/admin/launch', [$admin, 'launch']);
$router->post('/admin/launch/dismiss', [$admin, 'dismissLaunchChecklist']);
$router->post('/admin/demo-users/create', [$admin, 'createDemoUsers']);
$router->post('/admin/demo-users/remove', [$admin, 'removeDemoUsers']);
$router->post('/admin/membership-plans', [$admin, 'saveMembershipPlan']);

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$router->dispatch((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), is_string($path) ? $path : '/');
