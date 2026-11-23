<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Database\Migrator;
use App\Services\AdminBootstrapService;
use App\Services\ApiSecretCipher;
use App\Services\AuthenticationService;
use App\Services\DemoUserService;
use App\Services\IntegrationConfigService;
use App\Services\MembershipPlanService;
use App\Services\PlatformLaunchService;
use App\Services\RegistrationService;
use App\Services\StorageProvisioner;
use App\Services\StripeBillingService;
use App\Services\UserIdGenerator;
use App\Support\Env;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function app_db(): PDO
{
    global $pdo;

    return $pdo;
}

$envTestSuffix = strtoupper(bin2hex(random_bytes(6)));
$envTestKeys = [
    'exported' => 'GARWIN_TEST_EXPORTED_' . $envTestSuffix,
    'inline_comment' => 'GARWIN_TEST_INLINE_COMMENT_' . $envTestSuffix,
    'quoted_value' => 'GARWIN_TEST_QUOTED_VALUE_' . $envTestSuffix,
    'quoted_comment_value' => 'GARWIN_TEST_QUOTED_COMMENT_VALUE_' . $envTestSuffix,
    'empty_value' => 'GARWIN_TEST_EMPTY_VALUE_' . $envTestSuffix,
    'existing_value' => 'GARWIN_TEST_EXISTING_VALUE_' . $envTestSuffix,
];
$previousEnvironment = [];
$previousEnv = [];
foreach ($envTestKeys as $key) {
    $previousEnvironment[$key] = getenv($key);
    $previousEnv[$key] = array_key_exists($key, $_ENV) ? $_ENV[$key] : null;
}
$envFixture = tempnam(sys_get_temp_dir(), 'garwinai-env-test-');
if ($envFixture === false) {
    throw new RuntimeException('Unable to create the environment test fixture.');
}

try {
    $envContents = implode("\n", [
        '# ignored comment',
        'export ' . $envTestKeys['exported'] . '=exported-value',
        $envTestKeys['inline_comment'] . '=plain-value # ignored suffix',
        $envTestKeys['quoted_value'] . '="quoted # value"',
        $envTestKeys['quoted_comment_value'] . '="quoted # value" # trailing comment',
        $envTestKeys['empty_value'] . '=',
        $envTestKeys['existing_value'] . '=file-value',
        'INVALID_ENV_LINE',
        '',
    ]);
    if (file_put_contents($envFixture, $envContents) === false) {
        throw new RuntimeException('Unable to write the environment test fixture.');
    }
    putenv($envTestKeys['existing_value'] . '=process-value');
    Env::load($envFixture);

    assertTrue(Env::get($envTestKeys['exported']) === 'exported-value', 'The env loader must support export-prefixed assignments.');
    assertTrue(Env::get($envTestKeys['inline_comment']) === 'plain-value', 'The env loader must strip unquoted inline comments.');
    assertTrue(Env::get($envTestKeys['quoted_value']) === 'quoted # value', 'The env loader must preserve comment characters in quoted values.');
    assertTrue(Env::get($envTestKeys['quoted_comment_value']) === 'quoted # value', 'The env loader must ignore inline comments after quoted values.');
    assertTrue(Env::get($envTestKeys['empty_value']) === '', 'The env loader must preserve empty values.');
    assertTrue(Env::get($envTestKeys['existing_value']) === 'process-value', 'The env loader must not override existing process values.');
} finally {
    unlink($envFixture);
    foreach ($envTestKeys as $key) {
        if ($previousEnvironment[$key] === false) {
            putenv($key);
        } else {
            putenv($key . '=' . $previousEnvironment[$key]);
        }
        if ($previousEnv[$key] === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $previousEnv[$key];
        }
    }
}

$pdo = new PDO('sqlite::memory:', options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');
$migrations = (new Migrator($pdo))->migrate(dirname(__DIR__) . '/database/migrations');
assertTrue(
    $migrations === [
        '001_create_platform_foundation.php',
        '002_add_admin_launch_and_demo_users.php',
        '003_create_workspace_features.php',
        '004_create_calendar_events.php',
        '005_create_ai_assistant.php',
        '007_create_billing.php',
        '008_create_appzone_checkout.php',
        '009_seed_developer_app.php',
        '010_create_messages.php',
        '011_create_workspace_pages.php',
    ],
    'Foundation, admin, and workspace migrations must apply.'
);
assertTrue(
    (new Migrator($pdo))->migrate(dirname(__DIR__) . '/database/migrations') === [],
    'Applied migrations must be idempotent.'
);

$storageRoot = sys_get_temp_dir() . '/garwinai-test-' . bin2hex(random_bytes(8));
putenv('GARWIN_STORAGE_PATH=' . $storageRoot);
$storage = new StorageProvisioner(dirname(__DIR__));
putenv('APP_STORAGE_DRIVER=webdav');
putenv('APP_STORAGE_WEBDAV_URL=http://files.example.test/');
putenv('APP_STORAGE_WEBDAV_USERNAME=webdisk-test');
putenv('APP_STORAGE_WEBDAV_PASSWORD=not-a-real-password');
$webdavReadiness = (new StorageProvisioner(dirname(__DIR__)))->readiness($pdo);
assertTrue(
    !$webdavReadiness['ready'] && str_contains($webdavReadiness['detail'], 'HTTPS WebDAV endpoint'),
    'WebDAV must be selected explicitly and reject insecure or incomplete endpoint configuration.'
);
putenv('APP_STORAGE_DRIVER');
putenv('APP_STORAGE_WEBDAV_URL');
putenv('APP_STORAGE_WEBDAV_USERNAME');
putenv('APP_STORAGE_WEBDAV_PASSWORD');
$registration = new RegistrationService($pdo, new UserIdGenerator($pdo), $storage);
$user = $registration->register(
    'Test.User@example.com',
    'correct horse battery staple',
    'Test',
    'User',
    true
);

assertTrue((bool) preg_match('/\A[A-Za-z0-9]{9}\z/', $user['user_id']), 'Public user IDs must be nine alphanumeric characters.');
assertTrue($user['email'] === 'test.user@example.com', 'Emails must be normalized before storage.');
$userRow = $pdo->query('SELECT password_hash FROM users WHERE id = ' . (int) $user['id'])->fetch();
assertTrue(password_verify('correct horse battery staple', $userRow['password_hash']), 'Passwords must be hashed with a supported secure algorithm.');
assertTrue($userRow['password_hash'] !== 'correct horse battery staple', 'Passwords must never be stored in plaintext.');

$relatedRecords = [
    'memberships' => 'free',
    'user_preferences' => '{}',
    'dashboard_layouts' => '{"widgets":[]}',
    'ai_budgets' => '300',
    'storage_accounts' => '1073741824',
];
foreach ($relatedRecords as $table => $expected) {
    $statement = $pdo->prepare("SELECT * FROM {$table} WHERE user_id = :user_id");
    $statement->execute(['user_id' => $user['id']]);
    $record = $statement->fetch();
    assertTrue($record !== false, "Registration must initialize {$table}.");
    if ($table === 'memberships') {
        assertTrue($record['plan_key'] === $expected, 'New accounts must start on the Free plan.');
    } elseif ($table === 'user_preferences') {
        assertTrue($record['preferences_json'] === $expected, 'New accounts must start with default preferences.');
    } elseif ($table === 'dashboard_layouts') {
        assertTrue($record['layout_json'] === $expected, 'New accounts must start with a dashboard layout.');
    } elseif ($table === 'ai_budgets') {
        assertTrue((string) $record['budget_cents'] === $expected, 'Free accounts must initialize a $3.00 AI budget.');
    } else {
        assertTrue((string) $record['quota_bytes'] === $expected, 'Free accounts must initialize 1 GB of storage.');
    }
}

$folderNames = $pdo->query(
    'SELECT folder_name FROM storage_folders WHERE user_id = ' . (int) $user['id'] . ' ORDER BY folder_name'
)->fetchAll(PDO::FETCH_COLUMN);
$expectedFolders = ['Apps', 'Documents', 'Media', 'System', 'Themes', 'Widgets'];
assertTrue($folderNames === $expectedFolders, 'Registration must initialize the six private storage folders.');
$storagePath = $storageRoot . '/ACSD-73h5ZXp1Q/UCSD-' . $user['user_id'] . '-001';
assertTrue(is_dir($storagePath . '/Documents') && (fileperms($storagePath) & 0077) === 0, 'Private storage must be provisioned with restrictive permissions.');
$documentContents = $storage->listContents('UCSD-' . $user['user_id'] . '-001', 'Documents');
assertTrue($documentContents === [], 'Storage browsing must list an empty provisioned folder.');
file_put_contents($storagePath . '/Documents/notes.txt', 'fixture');
$documentContents = $storage->listContents('UCSD-' . $user['user_id'] . '-001', 'Documents');
assertTrue(
    count($documentContents) === 1 && $documentContents[0]['name'] === 'notes.txt' && $documentContents[0]['size'] === 7,
    'Storage browsing must return private file metadata.'
);
$privateFilePath = $storage->filePathForUser('UCSD-' . $user['user_id'] . '-001', 'Documents', 'notes.txt');
assertTrue($privateFilePath === $storagePath . '/Documents/notes.txt', 'Storage file lookup must stay inside the user folder.');
$deletedBytes = $storage->deleteFileForUser('UCSD-' . $user['user_id'] . '-001', 'Documents', 'notes.txt');
assertTrue($deletedBytes === 7 && !file_exists($privateFilePath), 'Storage files must be deletable with their byte size reported.');
$storage->createFolderForUser('UCSD-' . $user['user_id'] . '-001', 'Personal Files');
assertTrue(
    $storage->listContents('UCSD-' . $user['user_id'] . '-001', 'Personal Files') === [],
    'Users must be able to create private folders.'
);
$traversalRejected = false;
try {
    $storage->createFolderForUser('UCSD-' . $user['user_id'] . '-001', '../outside');
} catch (RuntimeException) {
    $traversalRejected = true;
}
assertTrue($traversalRejected, 'Storage must reject folder traversal attempts.');
$storage->removeEmptyFolderForUser('UCSD-' . $user['user_id'] . '-001', 'Personal Files');

$pdo->prepare(
    'INSERT INTO appzone_items (name, description, launch_url, price_cents, is_published, created_at, updated_at)
     VALUES (:name, :description, :launch_url, :price_cents, :is_published, :created_at, :updated_at)'
)->execute([
    'name' => 'Fixture app',
    'description' => 'Test listing',
    'launch_url' => 'https://example.test/app',
    'price_cents' => 0,
    'is_published' => 1,
    'created_at' => gmdate('Y-m-d H:i:s'),
    'updated_at' => gmdate('Y-m-d H:i:s'),
]);
$appId = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO appzone_ratings (app_id, user_id, rating, created_at, updated_at)
     VALUES (:app_id, :user_id, :rating, :created_at, :updated_at)'
)->execute([
    'app_id' => $appId,
    'user_id' => $user['id'],
    'rating' => 5,
    'created_at' => gmdate('Y-m-d H:i:s'),
    'updated_at' => gmdate('Y-m-d H:i:s'),
]);
assertTrue((int) $pdo->query('SELECT rating FROM appzone_ratings WHERE app_id = ' . $appId)->fetchColumn() === 5, 'AppZone ratings must persist 1-to-5 star values.');
$installStatement = $pdo->prepare(
    'INSERT INTO appzone_installs (app_id, user_id, installed_at)
     VALUES (:app_id, :user_id, :installed_at) ON CONFLICT (app_id, user_id) DO NOTHING'
);
$installStatement->execute(['app_id' => $appId, 'user_id' => $user['id'], 'installed_at' => gmdate('Y-m-d H:i:s')]);
$installStatement->execute(['app_id' => $appId, 'user_id' => $user['id'], 'installed_at' => gmdate('Y-m-d H:i:s')]);
assertTrue(
    (int) $pdo->query('SELECT COUNT(*) FROM appzone_installs WHERE app_id = ' . $appId)->fetchColumn() === 1,
    'An AppZone app can only be installed once per user.'
);

$_SESSION = ['user_id' => $user['id']];
$_GET = [];
ob_start();
(new App\Http\Controllers\AppZoneController())->index();
$appZoneHtml = (string) ob_get_clean();
assertTrue(
    str_contains($appZoneHtml, 'Top Items') && str_contains($appZoneHtml, 'Most Viewed')
        && str_contains($appZoneHtml, 'Most purchased') && str_contains($appZoneHtml, 'Fixture app')
        && str_contains($appZoneHtml, 'Garwin Developer'),
    'The AppZone catalog page must render its ranking sections, listings, and the Developer App.'
);
$developerAppId = (int) $pdo->query("SELECT id FROM appzone_items WHERE launch_url = '/developer'")->fetchColumn();
$installStatement->execute(['app_id' => $developerAppId, 'user_id' => $user['id'], 'installed_at' => gmdate('Y-m-d H:i:s')]);
assertTrue(
    App\Services\NavigationService::developerIsInstalled($pdo, (int) $user['id']),
    'Developer must count as installed once it is installed from AppZone.'
);
ob_start();
(new App\Http\Controllers\DeveloperController())->index();
$developerHtml = (string) ob_get_clean();
assertTrue(
    str_contains($developerHtml, '<h1>Developer</h1>') && str_contains($developerHtml, 'manifest.json')
        && str_contains($developerHtml, 'Never execute developer-supplied PHP')
        && str_contains($developerHtml, '<pre><code>'),
    'The Developer app must render the package guide, examples, and server-code safety rules.'
);

$navigationService = new App\Services\NavigationService($pdo);
assertTrue(
    $navigationService->pinnedKeys((int) $user['id']) === App\Services\NavigationService::DEFAULT_PINS,
    'New accounts must pin Wordbase, Spreadsheets, Codebase, Storage, and AppZone by default.'
);
assertTrue(!$navigationService->togglePin((int) $user['id'], 'calendar'), 'Calendar is fixed on the top bar and must not be pinnable.');
assertTrue(!$navigationService->togglePin((int) $user['id'], 'dashboard'), 'The top bar must hold at most 5 pinned apps.');
assertTrue($navigationService->togglePin((int) $user['id'], 'codebase'), 'Pinned apps must be removable.');
assertTrue($navigationService->togglePin((int) $user['id'], 'dashboard'), 'Workspace items must be pinnable once there is room.');
assertTrue(
    $navigationService->pinnedKeys((int) $user['id']) === ['wordbase', 'spreadsheets', 'storage', 'appzone', 'dashboard'],
    'Pin changes must persist in the user preferences.'
);
assertTrue(!$navigationService->togglePin((int) $user['id'], 'not-an-app'), 'Unknown items must not be pinnable.');
$pdo->prepare(
    'INSERT INTO messages (sender_user_id, recipient_user_id, subject, body, is_priority, read_at, created_at)
     VALUES (:sender, :recipient, :subject, :body, :priority, :read_at, :created_at)'
)->execute(['sender' => $user['id'], 'recipient' => $user['id'], 'subject' => 'Hello', 'body' => 'Fixture', 'priority' => 1, 'read_at' => null, 'created_at' => gmdate('Y-m-d H:i:s')]);
$navigationData = $navigationService->forUser($user);
assertTrue(
    $navigationData['messageCounts'] === ['new' => 1, 'read' => 0, 'priority' => 1],
    'Messenger counts must reflect unread, read, and priority messages.'
);
assertTrue(
    $navigationData['driveLabel'] === 'USD-' . $user['user_id'] . '-001' && isset($navigationData['items']['developer']),
    'The sidebar must label the storage drive and list Developer once installed.'
);
ob_start();
(new App\Http\Controllers\DashboardController())->index();
$dashboardHtml = (string) ob_get_clean();
assertTrue(
    !str_contains($dashboardHtml, 'AI budget') && !str_contains($dashboardHtml, 'spent_cents') && str_contains($dashboardHtml, 'User ID')
        && str_contains($dashboardHtml, 'Storage'),
    'The user dashboard must show storage and the User ID, and never AI cost.'
);
assertTrue(
    str_contains($dashboardHtml, 'Weather') && str_contains($dashboardHtml, 'Messages')
        && str_contains($dashboardHtml, 'Notifications') && str_contains($dashboardHtml, 'Support'),
    'The dashboard must render its core widgets.'
);
$calendarStart = gmdate('Y-m-d') . ' 10:00:00';
$calendarEnd = gmdate('Y-m-d') . ' 11:00:00';
$calendarInsert = $pdo->prepare(
    'INSERT INTO calendar_events
     (user_id, title, description, starts_at, ends_at, created_at, updated_at)
     VALUES (:user_id, :title, :description, :starts_at, :ends_at, :created_at, :updated_at)'
);
$calendarInsert->execute([
    'user_id' => $user['id'],
    'title' => 'Workspace planning',
    'description' => 'Review the next milestone.',
    'starts_at' => $calendarStart,
    'ends_at' => $calendarEnd,
    'created_at' => gmdate('Y-m-d H:i:s'),
    'updated_at' => gmdate('Y-m-d H:i:s'),
]);
$_GET = ['month' => gmdate('Y-m')];
ob_start();
(new App\Http\Controllers\CalendarController())->index();
$calendarHtml = (string) ob_get_clean();
assertTrue(
    str_contains($calendarHtml, 'Calendar') && str_contains($calendarHtml, 'Workspace planning')
        && str_contains($calendarHtml, 'Create an event') && str_contains($calendarHtml, 'Starts (UTC)'),
    'The calendar must render the selected month, its events, and the event form.'
);
$savedWidgets = [
    ['id' => 'support', 'size' => 'wide'],
    ['id' => 'notifications', 'size' => 'small'],
    ['id' => 'messages', 'size' => 'small'],
    ['id' => 'files', 'size' => 'small'],
    ['id' => 'clock', 'size' => 'small'],
    ['id' => 'weather', 'size' => 'small'],
    ['id' => 'overview', 'size' => 'full'],
];
$_POST = [
    '_token' => App\Support\Csrf::token(),
    'layout_json' => json_encode(['widgets' => $savedWidgets], JSON_THROW_ON_ERROR),
];
http_response_code(200);
ob_start();
(new App\Http\Controllers\DashboardController())->saveLayout();
ob_end_clean();
$savedLayout = json_decode((string) $pdo->query('SELECT layout_json FROM dashboard_layouts WHERE user_id = ' . (int) $user['id'])->fetchColumn(), true);
assertTrue(
    http_response_code() === 204 && $savedLayout['widgets'][0]['id'] === 'support',
    'Dashboard widget order and size must persist per user.'
);
ob_start();
(new App\Http\Controllers\SettingsController())->index();
$settingsHtml = (string) ob_get_clean();
assertTrue(str_contains($settingsHtml, 'Weather location') && str_contains($settingsHtml, 'Clock format'), 'Workspace preferences must render.');
$_GET = [];
ob_start();
(new App\Http\Controllers\StorageController())->index();
$storageHtml = (string) ob_get_clean();
assertTrue(str_contains($storageHtml, 'My Drive') && str_contains($storageHtml, 'Documents'), 'Storage must display the user’s provisioned folders.');

// Dashboard: any subset of widgets, renamed titles, and the version 1 → 2 upgrade.
$_POST = [
    '_token' => App\Support\Csrf::token(),
    'layout_json' => json_encode(['widgets' => [
        ['id' => 'notify', 'size' => 'small', 'title' => 'My inbox'],
        ['id' => 'clock', 'size' => 'small', 'title' => 'Clock'],
    ]], JSON_THROW_ON_ERROR),
];
http_response_code(200);
ob_start();
(new App\Http\Controllers\DashboardController())->saveLayout();
ob_end_clean();
$savedLayout = json_decode((string) $pdo->query('SELECT layout_json FROM dashboard_layouts WHERE user_id = ' . (int) $user['id'])->fetchColumn(), true);
assertTrue(
    http_response_code() === 204 && $savedLayout['version'] === 3 && count($savedLayout['widgets']) === 2
        && $savedLayout['widgets'][0]['title'] === 'My inbox' && $savedLayout['widgets'][1]['title'] === '',
    'Dashboards must save any set of widgets with renamed titles; a default title is stored as blank.'
);
$_POST['layout_json'] = json_encode(['widgets' => [['id' => 'clock', 'size' => 'small', 'title' => str_repeat('x', 41)]]], JSON_THROW_ON_ERROR);
http_response_code(200);
ob_start();
(new App\Http\Controllers\DashboardController())->saveLayout();
ob_end_clean();
assertTrue(http_response_code() === 422, 'Widget titles longer than 40 characters must be rejected.');
$pdo->exec("UPDATE dashboard_layouts SET layout_json = '{\"widgets\":[{\"id\":\"overview\",\"size\":\"wide\"},{\"id\":\"weather\",\"size\":\"small\"},{\"id\":\"clock\",\"size\":\"small\"}]}' WHERE user_id = " . (int) $user['id']);
$_POST = [];
http_response_code(200);
ob_start();
(new App\Http\Controllers\DashboardController())->index();
$dashboardHtml = (string) ob_get_clean();
preg_match('/data-layout="([^"]*)"/', $dashboardHtml, $layoutMatch);
$shownLayout = json_decode(html_entity_decode($layoutMatch[1] ?? '[]'), true);
assertTrue(
    array_column($shownLayout, 'id') === ['welcome', 'overview', 'notify', 'clock', 'weather'],
    'Older dashboards must gain Notify where Weather was (Weather moves to the end) and the Welcome widget on top.'
);
assertTrue(
    str_contains($dashboardHtml, 'workspace-search') && str_contains($dashboardHtml, 'Your apps')
        && str_contains($dashboardHtml, 'data-widget-gallery') && str_contains($dashboardHtml, 'Add a page'),
    'The dashboard must show the search bar and the Apps, Widgets and Pages menus.'
);

// Pages: create, save, duplicate, delete; content is checked per type.
$pageService = new App\Services\WorkspacePageService($pdo);
$docId = $pageService->create((int) $user['id'], 'doc');
$sheetId = $pageService->create((int) $user['id'], 'sheet');
$calendarId = $pageService->create((int) $user['id'], 'calendar');
assertTrue($docId !== null && $sheetId !== null && $calendarId !== null, 'Docs, spreadsheets and calendars must be creatable as pages.');
assertTrue($pageService->create((int) $user['id'], 'video') === null, 'Unknown page types must be refused.');
assertTrue($pageService->save((int) $user['id'], $sheetId, 'Budget', '{"cells":{"A1":"10","B2":"=SUM(A1:A3)"}}') === null, 'Spreadsheet cells must save.');
assertTrue($pageService->save((int) $user['id'], $sheetId, 'Budget', '{"cells":{"Z99":"1"}}') !== null, 'Cells outside the grid must be refused.');
assertTrue($pageService->save((int) $user['id'], $calendarId, 'Team', '{"events":{"2026-11-23":["Standup"]}}') === null, 'Calendar page events must save.');
assertTrue($pageService->save((int) $user['id'], $calendarId, 'Team', '{"events":{"2026-02-30":["Nope"]}}') !== null, 'Impossible dates must be refused.');
$copyId = $pageService->duplicate((int) $user['id'], $sheetId);
$copy = $copyId === null ? null : $pageService->find((int) $user['id'], $copyId);
assertTrue($copy !== null && $copy['title'] === 'Budget (copy)' && str_contains($copy['content'], '=SUM(A1:A3)'), 'Duplicating a page must copy its content.');
assertTrue($pageService->delete((int) $user['id'], $copyId) && $pageService->find((int) $user['id'], $copyId) === null, 'Pages must be deletable.');
assertTrue(!$pageService->delete((int) $user['id'] + 999, $docId), 'Users must not delete pages that are not theirs.');
assertTrue(count($pageService->listForUser((int) $user['id'])) === 3, 'The Pages list must show the user’s pages.');

// Windows: Note and Page windows save with their text; bad ones are refused.
$_POST = [
    '_token' => App\Support\Csrf::token(),
    'layout_json' => json_encode(['widgets' => [
        ['id' => 'welcome', 'size' => 'full', 'title' => 'Hello'],
        ['id' => 'window-0a1b2c3d', 'size' => 'small', 'title' => 'Ideas', 'kind' => 'note', 'note' => 'Ship Pages'],
        ['id' => 'window-0a1b2c3e', 'size' => 'wide', 'title' => 'Budget', 'kind' => 'page', 'page_id' => $sheetId],
    ]], JSON_THROW_ON_ERROR),
];
http_response_code(200);
ob_start();
(new App\Http\Controllers\DashboardController())->saveLayout();
ob_end_clean();
$savedLayout = json_decode((string) $pdo->query('SELECT layout_json FROM dashboard_layouts WHERE user_id = ' . (int) $user['id'])->fetchColumn(), true);
assertTrue(
    http_response_code() === 204 && $savedLayout['widgets'][1]['note'] === 'Ship Pages' && $savedLayout['widgets'][2]['page_id'] === $sheetId,
    'Note and Page windows must save with their contents.'
);
$_POST['layout_json'] = json_encode(['widgets' => [['id' => 'window-zzzz', 'size' => 'small', 'title' => 'X', 'kind' => 'note', 'note' => '']]], JSON_THROW_ON_ERROR);
http_response_code(200);
ob_start();
(new App\Http\Controllers\DashboardController())->saveLayout();
ob_end_clean();
assertTrue(http_response_code() === 422, 'Malformed window ids must be refused.');
$_POST = [];
ob_start();
(new App\Http\Controllers\DashboardController())->index();
$dashboardHtml = (string) ob_get_clean();
assertTrue(
    str_contains($dashboardHtml, 'Ship Pages') && str_contains($dashboardHtml, 'Open spreadsheet') && str_contains($dashboardHtml, 'widget-welcome'),
    'The dashboard must show the Welcome widget, Note windows and Page windows.'
);

// Menu links: added, edited, moved and removed; unsafe addresses are refused.
$menu = new App\Services\NavigationService($pdo);
assertTrue($menu->saveMenuLink((int) $user['id'], null, 'Docs', 'docs.garwinai.com', 'globe', 'emerald') === null, 'Menu links must be addable.');
assertTrue($menu->saveMenuLink((int) $user['id'], null, 'Team', '/pages/view?id=1', 'users', 'purple') === null, 'Links to pages here must be addable.');
assertTrue($menu->saveMenuLink((int) $user['id'], null, 'Bad', 'javascript:alert(1)', 'link', 'sky') !== null, 'Script links must be refused.');
$links = $menu->menuLinks((int) $user['id']);
assertTrue(count($links) === 2 && $links[0]['url'] === 'https://docs.garwinai.com', 'Bare domains must become https links.');
assertTrue($menu->saveMenuLink((int) $user['id'], $links[0]['id'], 'Help docs', $links[0]['url'], 'help', 'sky') === null && $menu->menuLinks((int) $user['id'])[0]['label'] === 'Help docs', 'Menu links must be renamable.');
assertTrue($menu->moveMenuLink((int) $user['id'], $links[1]['id'], -1) && $menu->menuLinks((int) $user['id'])[0]['label'] === 'Team', 'Menu links must be reorderable.');
assertTrue($menu->deleteMenuLink((int) $user['id'], $links[1]['id']) && count($menu->menuLinks((int) $user['id'])) === 1, 'Menu links must be removable.');

// Apps: turning an app off hides it from the top bar until it is turned back on.
$appsNavigation = new App\Services\NavigationService($pdo);
assertTrue($appsNavigation->toggleActive((int) $user['id'], 'wordbase'), 'Apps must be deactivatable.');
assertTrue(!in_array('wordbase', $appsNavigation->forUser($user)['pins'], true), 'Deactivated apps must leave the top bar.');
assertTrue($appsNavigation->findApp((int) $user['id'], 'wordbase')['active'] === false, 'Deactivated apps must show as off.');
assertTrue($appsNavigation->toggleActive((int) $user['id'], 'wordbase') && in_array('wordbase', $appsNavigation->forUser($user)['pins'], true), 'Apps must be reactivatable.');
assertTrue(!$appsNavigation->toggleActive((int) $user['id'], 'not-an-app'), 'Unknown apps must not be toggled.');

$generator = new UserIdGenerator($pdo);
$existingId = $user['user_id'];
$newId = $generator->generate();
assertTrue($newId !== $existingId && (bool) preg_match('/\A[A-Za-z0-9]{9}\z/', $newId), 'Public ID generation must check for collisions.');

$testKey = 'base64:' . base64_encode(random_bytes(32));
putenv('APP_KEY=' . $testKey);
putenv('APP_URL=https://www.garwinai.com');
putenv('SESSION_SECURE_COOKIE=true');
putenv('APP_DEBUG=false');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__) . '/public';
$cipher = new ApiSecretCipher();
$integrationConfigs = new IntegrationConfigService($pdo, $cipher);
$stripeCredentials = [
    'secret_key' => 'sk_test_local_fixture_secret',
    'publishable_key' => 'pk_test_local_fixture',
    'webhook_secret' => 'whsec_local_fixture',
];
$integrationConfigs->save('stripe', $stripeCredentials);
$storedCredentials = $pdo->query(
    "SELECT credentials_json FROM integration_configs WHERE provider_key = 'stripe'"
)->fetchColumn();
assertTrue(!str_contains($storedCredentials, $stripeCredentials['secret_key']), 'API keys must be encrypted at rest.');
assertTrue(
    $integrationConfigs->credentials('stripe') === $stripeCredentials,
    'Encrypted provider credentials must decrypt for server-side verification.'
);
$integrationConfigs->save('resend', [
    'api_key' => 're_local_fixture_secret',
    'from_email' => 'noreply@example.com',
]);
$integrationConfigs->save('openai', ['api_key' => 'sk-local-openai-fixture']);
foreach (['stripe', 'resend', 'openai'] as $provider) {
    $integrationConfigs->recordTest($provider, true, 'Provider credentials verified successfully.');
}
$plans = new MembershipPlanService($pdo);
$proPlan = $plans->get('pro');
assertTrue(
    $proPlan['price_cents'] === 4999 && $proPlan['storage_quota_bytes'] === 26843545600
        && $proPlan['ai_budget_cents'] === 800 && $proPlan['team_seats'] === 3,
    'Billing must initialize the documented Pro price and entitlements.'
);
$plans->update('pro', 5999, 30, 900, 5, true);
$billingRequests = [];
$billingRequester = static function (
    string $method,
    string $path,
    array $parameters,
    string $secretKey,
    ?string $idempotencyKey
) use (&$billingRequests): array {
    assertTrue($secretKey === 'sk_test_local_fixture_secret', 'Billing requests must use the encrypted Stripe secret.');
    $billingRequests[] = ['method' => $method, 'path' => $path, 'parameters' => $parameters, 'key' => $idempotencyKey];

    return match ($path) {
        'customers' => [
            'status' => 200,
            'body' => json_encode(['id' => 'cus_local_fixture'], JSON_THROW_ON_ERROR),
        ],
        'checkout/sessions' => (static function () use ($parameters): array {
            if ($parameters['mode'] === 'payment') {
                assertTrue(
                    $parameters['line_items'][0]['price_data']['unit_amount'] === 2500,
                    'AppZone checkout must use the current published item price.'
                );

                return [
                    'status' => 200,
                    'body' => json_encode([
                        'id' => 'cs_appzone_local_fixture',
                        'url' => 'https://checkout.stripe.com/cs_appzone_local_fixture',
                        'expires_at' => time() + 3600,
                    ], JSON_THROW_ON_ERROR),
                ];
            }
            assertTrue(
                $parameters['line_items'][0]['price_data']['unit_amount'] === 5999,
                'Checkout must use the currently configured Pro price.'
            );

            return [
                'status' => 200,
                'body' => json_encode(['url' => 'https://checkout.stripe.com/c/pay_local_fixture'], JSON_THROW_ON_ERROR),
            ];
        })(),
        'billing_portal/sessions' => [
            'status' => 200,
            'body' => json_encode(['url' => 'https://billing.stripe.com/p/session_local_fixture'], JSON_THROW_ON_ERROR),
        ],
        default => ['status' => 404, 'body' => '{}'],
    };
};
$billing = new StripeBillingService($pdo, $integrationConfigs, $billingRequester);
assertTrue(
    $billing->createCheckoutSession($user['id'], $user['email'], 'Test User')
        === 'https://checkout.stripe.com/c/pay_local_fixture',
    'Pro membership checkout must use the Stripe-hosted checkout URL.'
);
assertTrue(
    $billing->createPortalSession($user['id']) === 'https://billing.stripe.com/p/session_local_fixture',
    'Customers must be able to manage their Stripe subscription through the hosted portal.'
);
$pdo->prepare(
    'INSERT INTO appzone_items (name, description, launch_url, price_cents, is_published, created_at, updated_at)
     VALUES (:name, :description, :launch_url, :price_cents, :is_published, :created_at, :updated_at)'
)->execute([
    'name' => 'Paid AppZone fixture',
    'description' => 'A paid marketplace item.',
    'launch_url' => 'https://example.test/paid-app',
    'price_cents' => 2500,
    'is_published' => 1,
    'created_at' => gmdate('Y-m-d H:i:s'),
    'updated_at' => gmdate('Y-m-d H:i:s'),
]);
$paidAppId = (int) $pdo->lastInsertId();
assertTrue(
    $billing->createAppZoneCheckoutSession($user['id'], $user['email'], 'Test User', $paidAppId)
        === 'https://checkout.stripe.com/cs_appzone_local_fixture',
    'A paid AppZone item must create a Stripe-hosted one-time checkout session.'
);
$appZoneRequestCount = count($billingRequests);
assertTrue(
    $billing->createAppZoneCheckoutSession($user['id'], $user['email'], 'Test User', $paidAppId)
        === 'https://checkout.stripe.com/cs_appzone_local_fixture'
        && count($billingRequests) === $appZoneRequestCount,
    'Repeated AppZone checkout requests must reuse the active Stripe session.'
);
$pendingPurchase = $pdo->query(
    'SELECT purchase_id, status FROM appzone_checkout_sessions WHERE app_id = ' . $paidAppId
)->fetch();
assertTrue(
    $pendingPurchase !== false && $pendingPurchase['status'] === 'pending'
        && (int) $pdo->query('SELECT COUNT(*) FROM appzone_installs WHERE app_id = ' . $paidAppId)->fetchColumn() === 0,
    'Starting checkout must not grant access before Stripe confirms payment.'
);
$appZoneSignatureTimestamp = time();
$unpaidAppZoneEvent = [
    'id' => 'evt_appzone_unpaid_fixture',
    'type' => 'checkout.session.completed',
    'created' => time(),
    'data' => ['object' => [
        'id' => 'cs_appzone_local_fixture',
        'mode' => 'payment',
        'payment_status' => 'unpaid',
        'amount_total' => 2500,
        'currency' => 'usd',
        'metadata' => ['appzone_purchase_id' => $pendingPurchase['purchase_id']],
    ]],
];
$unpaidAppZonePayload = json_encode($unpaidAppZoneEvent, JSON_THROW_ON_ERROR);
$unpaidAppZoneSignature = hash_hmac(
    'sha256',
    $appZoneSignatureTimestamp . '.' . $unpaidAppZonePayload,
    $stripeCredentials['webhook_secret']
);
assertTrue(
    $billing->processWebhook(
        $unpaidAppZonePayload,
        't=' . $appZoneSignatureTimestamp . ',v1=' . $unpaidAppZoneSignature
    ) && (int) $pdo->query('SELECT COUNT(*) FROM appzone_installs WHERE app_id = ' . $paidAppId)->fetchColumn() === 0,
    'A completed but unpaid Stripe session must not grant an AppZone install.'
);
$appZonePurchaseEvent = [
    'id' => 'evt_appzone_purchase_fixture',
    'type' => 'checkout.session.completed',
    'created' => time(),
    'data' => ['object' => [
        'id' => 'cs_appzone_local_fixture',
        'mode' => 'payment',
        'payment_status' => 'paid',
        'amount_total' => 2500,
        'currency' => 'usd',
        'metadata' => ['appzone_purchase_id' => $pendingPurchase['purchase_id']],
    ]],
];
$appZonePurchasePayload = json_encode($appZonePurchaseEvent, JSON_THROW_ON_ERROR);
$appZonePurchaseSignature = hash_hmac(
    'sha256',
    $appZoneSignatureTimestamp . '.' . $appZonePurchasePayload,
    $stripeCredentials['webhook_secret']
);
assertTrue(
    $billing->processWebhook(
        $appZonePurchasePayload,
        't=' . $appZoneSignatureTimestamp . ',v1=' . $appZonePurchaseSignature
    ),
    'A verified, paid AppZone Checkout event must be processed.'
);
assertTrue(
    (int) $pdo->query('SELECT COUNT(*) FROM appzone_installs WHERE app_id = ' . $paidAppId)->fetchColumn() === 1
        && (int) $pdo->query('SELECT purchase_count FROM appzone_items WHERE id = ' . $paidAppId)->fetchColumn() === 1,
    'A confirmed AppZone payment must grant one install and increment purchase rankings.'
);
assertTrue(
    !$billing->processWebhook(
        $appZonePurchasePayload,
        't=' . $appZoneSignatureTimestamp . ',v1=' . $appZonePurchaseSignature
    ),
    'AppZone purchase webhook delivery must be idempotent.'
);
$plans->update('pro', 4999, 25, 800, 3, true);
$subscriptionEvent = [
    'id' => 'evt_subscription_active_fixture',
    'type' => 'customer.subscription.updated',
    'created' => time(),
    'data' => ['object' => [
        'id' => 'sub_local_fixture',
        'customer' => 'cus_local_fixture',
        'status' => 'active',
        'current_period_end' => time() + 30 * 86400,
        'cancel_at_period_end' => false,
        'metadata' => ['garwin_user_id' => (string) $user['id'], 'plan_key' => 'pro'],
    ]],
];
$subscriptionPayload = json_encode($subscriptionEvent, JSON_THROW_ON_ERROR);
$signatureTimestamp = time();
$signature = hash_hmac('sha256', $signatureTimestamp . '.' . $subscriptionPayload, $stripeCredentials['webhook_secret']);
assertTrue(
    $billing->processWebhook($subscriptionPayload, 't=' . $signatureTimestamp . ',v1=' . $signature),
    'A valid Stripe subscription webhook must be processed.'
);
assertTrue(
    $billing->membership($user['id'])['plan_key'] === 'pro'
        && (int) $pdo->query('SELECT quota_bytes FROM storage_accounts WHERE user_id = ' . (int) $user['id'])->fetchColumn() === 26843545600
        && (int) $pdo->query('SELECT budget_cents FROM ai_budgets WHERE user_id = ' . (int) $user['id'])->fetchColumn() === 800,
    'A verified active subscription must grant Pro plan entitlements.'
);
$pendingCancellationEvent = $subscriptionEvent;
$pendingCancellationEvent['id'] = 'evt_subscription_cancel_pending_fixture';
$pendingCancellationEvent['type'] = 'customer.subscription.updated';
$pendingCancellationEvent['data']['object']['cancel_at_period_end'] = 'true';
$pendingCancellationPayload = json_encode($pendingCancellationEvent, JSON_THROW_ON_ERROR);
$pendingCancellationSignature = hash_hmac(
    'sha256',
    $signatureTimestamp . '.' . $pendingCancellationPayload,
    $stripeCredentials['webhook_secret']
);
assertTrue(
    $billing->processWebhook(
        $pendingCancellationPayload,
        't=' . $signatureTimestamp . ',v1=' . $pendingCancellationSignature
    ) && $billing->membership($user['id'])['cancel_at_period_end'] === true,
    'String boolean cancellation flags from Stripe must be normalized before membership updates.'
);
assertTrue(
    !$billing->processWebhook($subscriptionPayload, 't=' . $signatureTimestamp . ',v1=' . $signature),
    'Stripe webhook event delivery must be idempotent.'
);
$invalidWebhookRejected = false;
try {
    $billing->processWebhook($subscriptionPayload, 't=' . $signatureTimestamp . ',v1=' . str_repeat('0', 64));
} catch (InvalidArgumentException) {
    $invalidWebhookRejected = true;
}
assertTrue($invalidWebhookRejected, 'Unsigned Stripe events must not change memberships.');
$cancellationEvent = $subscriptionEvent;
$cancellationEvent['id'] = 'evt_subscription_deleted_fixture';
$cancellationEvent['type'] = 'customer.subscription.deleted';
$cancellationEvent['created'] = $signatureTimestamp + 1;
$cancellationEvent['data']['object']['status'] = 'canceled';
$cancellationPayload = json_encode($cancellationEvent, JSON_THROW_ON_ERROR);
$cancellationSignature = hash_hmac(
    'sha256',
    $signatureTimestamp . '.' . $cancellationPayload,
    $stripeCredentials['webhook_secret']
);
assertTrue(
    $billing->processWebhook(
        $cancellationPayload,
        't=' . $signatureTimestamp . ',v1=' . $cancellationSignature
    ) && $billing->membership($user['id'])['plan_key'] === 'free',
    'A verified Stripe cancellation must return the account to the Free plan.'
);
$plans->update('pro', 4999, 25, 800, 3, true);
$assistantService = new App\Services\OpenAiChatService(
    $integrationConfigs,
    static function (string $apiKey, array $payload): array {
        assertTrue($apiKey === 'sk-local-openai-fixture', 'The assistant must use the encrypted OpenAI credential.');
        assertTrue($payload['model'] === 'gpt-4o-mini', 'The assistant must use the configured supported model.');
        assertTrue(
            $payload['messages'][1] === ['role' => 'user', 'content' => 'Hello, GarwinAI.'],
            'The assistant must send the current user message to OpenAI.'
        );

        return [
            'status' => 200,
            'body' => json_encode([
                'choices' => [['message' => ['content' => 'Hello!']]],
            ], JSON_THROW_ON_ERROR),
        ];
    }
);
assertTrue(
    $assistantService->reply([], 'Hello, GarwinAI.') === 'Hello!',
    'The assistant must return the provider response text.'
);
$rateLimitedAssistant = new App\Services\OpenAiChatService(
    $integrationConfigs,
    static fn (string $apiKey, array $payload): array => ['status' => 429, 'body' => '{}']
);
$rateLimitMessage = '';
try {
    $rateLimitedAssistant->reply([], 'Try again.');
} catch (RuntimeException $exception) {
    $rateLimitMessage = $exception->getMessage();
}
assertTrue(
    str_contains($rateLimitMessage, 'rate-limiting'),
    'The assistant must report provider rate limits without exposing raw provider responses.'
);

$now = gmdate('Y-m-d H:i:s');
$pdo->prepare(
    'INSERT INTO ai_conversations (user_id, title, created_at, updated_at)
     VALUES (:user_id, :title, :created_at, :updated_at)'
)->execute([
    'user_id' => $user['id'],
    'title' => 'A saved conversation',
    'created_at' => $now,
    'updated_at' => $now,
]);
$conversationId = (int) $pdo->lastInsertId();
$pdo->prepare(
    'INSERT INTO ai_messages (conversation_id, role, content, created_at)
     VALUES (:conversation_id, :role, :content, :created_at)'
)->execute([
    'conversation_id' => $conversationId,
    'role' => 'assistant',
    'content' => 'This reply belongs to your account.',
    'created_at' => $now,
]);
$_GET = ['conversation' => (string) $conversationId];
ob_start();
(new App\Http\Controllers\AssistantController())->index();
$assistantHtml = (string) ob_get_clean();
assertTrue(
    str_contains($assistantHtml, 'AI Assistant') && str_contains($assistantHtml, 'A saved conversation')
        && str_contains($assistantHtml, 'This reply belongs to your account.')
        && str_contains($assistantHtml, 'New conversation'),
    'The assistant page must render saved private conversations and a new conversation action.'
);

$launch = new PlatformLaunchService($pdo, $integrationConfigs, $cipher, $storage);
$requirements = $launch->requirements();
assertTrue(
    array_diff(['php_runtime', 'database', 'migrations', 'web_root', 'private_storage'], array_column($requirements, 'key')) === [],
    'Launch checklist must cover runtime, database, migration, web-root, and storage requirements.'
);
assertTrue(
    count(array_filter($requirements, static fn (array $requirement): bool => $requirement['fix'] === '')) === 0,
    'Every launch checklist item must include repair guidance.'
);
assertTrue(
    count(array_filter($requirements, static fn (array $requirement): bool => !$requirement['ready'])) === 0,
    'A fully configured local platform must pass the runtime launch checks.'
);
$_SERVER['DOCUMENT_ROOT'] = sys_get_temp_dir();
$webRootRequirement = array_values(array_filter(
    $launch->requirements(),
    static fn (array $requirement): bool => $requirement['key'] === 'web_root'
))[0];
assertTrue(!$webRootRequirement['ready'], 'The launch checklist must flag a web root outside public/.');
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__) . '/public';
assertTrue($launch->isLaunchReady(), 'Launch requires secure app configuration and verified provider credentials.');
$launch->markLaunchReady();
assertTrue($launch->isLaunched(), 'A ready platform must persist its in-app launch state.');
$integrationConfigs->save('stripe', ['secret_key' => 'sk_test_rotated_fixture_secret']);
assertTrue(!$launch->isLaunched(), 'Changing provider credentials must invalidate the launch-ready state until reverified.');

$adminRegistration = new RegistrationService($pdo, $generator, $storage);
$adminBootstrap = new AdminBootstrapService($pdo, $adminRegistration);
assertTrue(
    $adminBootstrap->bootstrap('admin@example.test', 'test-only-admin-password-2026'),
    'Bootstrap must provision a new Super Admin account.'
);
assertTrue(
    !$adminBootstrap->bootstrap('admin@example.test', 'test-only-admin-password-2026'),
    'Admin bootstrap must be idempotent.'
);
$adminAuth = (new AuthenticationService($pdo))->attempt(
    'admin@example.test',
    'test-only-admin-password-2026',
    '192.0.2.20'
);
assertTrue($adminAuth !== null && (bool) $adminAuth['is_admin'], 'Only provisioned admin accounts receive the admin role.');
$pdo->prepare(
    'INSERT INTO admin_users (user_id, role_key, created_at) VALUES (:user_id, :role_key, :created_at)'
)->execute([
    'user_id' => $user['id'],
    'role_key' => 'content_admin',
    'created_at' => gmdate('Y-m-d H:i:s'),
]);
$regularRoleAuth = (new AuthenticationService($pdo))->attempt(
    'test.user@example.com',
    'correct horse battery staple',
    '192.0.2.21'
);
assertTrue($regularRoleAuth !== null && !$regularRoleAuth['is_admin'], 'Non-Super Admin roles must not receive Super Admin access.');
$pdo->prepare('DELETE FROM admin_users WHERE user_id = :user_id')->execute(['user_id' => $user['id']]);

$demoUsers = new DemoUserService($pdo, $registration, $storage);
assertTrue($demoUsers->create(3) === 3 && $demoUsers->count() === 3, 'Admin demo-user creation must create the requested count.');
assertTrue(
    count(array_filter($demoUsers->list(), static fn (array $demo): bool => str_ends_with($demo['email'], '@example.invalid'))) === 3,
    'Demo accounts must use reserved non-deliverable email addresses.'
);
assertTrue($demoUsers->removeAll() === 3 && $demoUsers->count() === 0, 'Removing demo users must delete all and only marked demo accounts.');

$auth = new AuthenticationService($pdo);
assertTrue(
    $auth->attempt('TEST.USER@example.com', 'correct horse battery staple', '127.0.0.1') !== null,
    'Valid credentials should authenticate case-insensitively by email.'
);
assertTrue(
    $auth->attempt('test.user@example.com', 'incorrect password', '127.0.0.1') === null,
    'Invalid credentials must not authenticate.'
);
for ($attempt = 0; $attempt < 10; $attempt++) {
    assertTrue(
        $auth->attempt('test.user@example.com', 'incorrect password', '192.0.2.10') === null,
        'Incorrect credentials must not authenticate during throttling.'
    );
}
assertTrue(
    $auth->attempt('test.user@example.com', 'correct horse battery staple', '192.0.2.10') === null,
    'Login throttling must block further attempts for the active limit window.'
);
assertTrue(
    $auth->attempt('test.user@example.com', 'correct horse battery staple', '192.0.2.11') !== null,
    'Login throttling must not block a different client address.'
);

$adminStorage = $pdo->prepare('SELECT storage_key FROM storage_accounts WHERE user_id = :user_id');
$adminStorage->execute(['user_id' => $adminAuth['id']]);
$storage->removeForUser((string) $adminStorage->fetchColumn());
$storage->removeForUser('UCSD-' . $user['user_id'] . '-001');
assertTrue(!file_exists($storagePath), 'A provisioned user storage directory must be removable during account cleanup.');
rmdir($storageRoot . '/ACSD-73h5ZXp1Q');
rmdir($storageRoot);
putenv('GARWIN_STORAGE_PATH');
putenv('APP_KEY');
putenv('APP_URL');
putenv('SESSION_SECURE_COOKIE');
putenv('APP_DEBUG');

fwrite(STDOUT, "All foundation tests passed.\n");
