<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f5f7">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="manifest" href="/site.webmanifest">
    <title><?= e(($title ?? 'Workspace') . ' · ' . $appName) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php
$currentPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$installedApps = [];
$storageDrive = null;
if ($user !== null && !$user['is_admin']) {
    try {
        $appsStatement = app_db()->prepare(
            'SELECT i.name, i.launch_url
             FROM appzone_installs x JOIN appzone_items i ON i.id = x.app_id
             WHERE x.user_id = :user_id AND i.is_published = 1
             ORDER BY x.installed_at, x.id'
        );
        $appsStatement->execute(['user_id' => $user['id']]);
        $installedApps = $appsStatement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException) {
        $installedApps = [];
    }
    try {
        $driveStatement = app_db()->prepare('SELECT storage_key FROM storage_accounts WHERE user_id = :user_id');
        $driveStatement->execute(['user_id' => $user['id']]);
        $storageDrive = $driveStatement->fetchColumn() ?: null;
    } catch (PDOException) {
        $storageDrive = null;
    }
}
$planKey = $user !== null && !$user['is_admin'] ? App\Support\BuiltInApps::planKeyFor((int) $user['id']) : App\Support\BuiltInApps::FREE_PLAN;
$defaultApps = App\Support\BuiltInApps::forPlan($planKey);
$pinnedApps = array_slice($installedApps, 0, 5);
$appPaths = array_values(array_filter(array_map(
    static fn (array $app): string => (string) parse_url((string) $app['launch_url'], PHP_URL_PATH),
    $installedApps
), static fn (string $path): bool => str_starts_with($path, '/')
    && !in_array($path, ['/dashboard', '/assistant', '/messages', '/calendar', '/storage', '/settings', '/billing'], true)));
$appPaths = [...$appPaths, ...array_values(array_map(static fn (array $app): string => $app['path'], $defaultApps))];
$navActive = static fn (string ...$paths): bool => in_array($currentPath, $paths, true);
$navAttrs = static fn (bool $active): string => $active ? 'class="nav-link active" aria-current="page"' : 'class="nav-link"';
?>
<?php if ($user !== null): ?>
    <div class="app-frame">
    <header class="topbar" aria-label="Workspace toolbar">
        <?php if (!$user['is_admin']): ?>
            <nav class="workspace-shortcuts" aria-label="Workspace shortcuts">
                <a class="workspace-shortcut <?= $currentPath === '/dashboard' ? 'active' : '' ?>" href="/dashboard" aria-label="Dashboard" title="Dashboard" <?= $currentPath === '/dashboard' ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/></svg>
                </a>
                <button class="workspace-shortcut" type="button" aria-label="Search" title="Search — coming soon" disabled>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/></svg>
                </button>
                <a class="workspace-shortcut <?= $currentPath === '/calendar' ? 'active' : '' ?>" href="/calendar" aria-label="Calendar" title="Calendar" <?= $currentPath === '/calendar' ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/></svg>
                </a>
                <a class="workspace-shortcut <?= $currentPath === '/assistant' ? 'active' : '' ?>" href="/assistant" aria-label="AI Assistant" title="AI Assistant" <?= $currentPath === '/assistant' ? 'aria-current="page"' : 'data-assistant-open' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2Z"/><path d="m19 15 .9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/></svg>
                </a>
                <?php if (isset($defaultApps['wordbase'])): ?>
                <a class="workspace-shortcut <?= $currentPath === '/wordbase' ? 'active' : '' ?>" href="/wordbase" aria-label="Wordbase" title="Wordbase" <?= $currentPath === '/wordbase' ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v12A1.5 1.5 0 0 1 19 19.5H5A1.5 1.5 0 0 1 3.5 18V6A1.5 1.5 0 0 1 5 4.5Zm0 3h14M8 12h8M8 8h4"/></svg>
                </a>
                <?php endif; ?>
                <a class="workspace-shortcut <?= $currentPath === '/storage' ? 'active' : '' ?>" href="/storage" aria-label="Storage" title="Storage" <?= $currentPath === '/storage' ? 'aria-current="page"' : '' ?>>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v5H4zM4 12h16v7H4z"/><path d="M8 7.5h.01M8 15.5h.01"/></svg>
                </a>
            </nav>
        <?php else: ?>
            <span class="brand compact"><span>Garwin</span><strong>AI</strong></span>
            <span class="topbar-note">Super Admin</span>
        <?php endif; ?>
        <a class="support-link" href="mailto:<?= e($supportEmail) ?>">Help &amp; support</a>
        <?php if (!$user['is_admin'] && $pinnedApps !== []): ?>
            <nav class="pinned-apps" aria-label="Your apps">
                <?php foreach ($pinnedApps as $app): ?>
                    <a class="pinned-app-link" href="<?= e($app['launch_url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($app['name']) ?>" aria-label="<?= e($app['name']) ?>">
                        <?= e(mb_strtoupper(mb_substr($app['name'], 0, 1))) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </header>
    <div class="app-shell">
        <aside class="sidebar" aria-label="Main navigation">
            <a class="brand" href="/dashboard" aria-label="Garwin AI dashboard"><span>Garwin</span><strong>AI</strong></a>
            <?php if ($user['is_admin']): ?>
                <p class="nav-label">ADMINISTRATION</p>
                <a class="nav-link" href="/admin">Admin dashboard</a>
                <a class="nav-link" href="/admin/settings?tab=launch">Admin settings</a>
                <a class="nav-link" href="/admin/settings?tab=demo-users">Demo Users</a>
                <a class="nav-link <?= $currentPath === '/admin/appzone' ? 'active' : '' ?>" href="/admin/appzone" <?= $currentPath === '/admin/appzone' ? 'aria-current="page"' : '' ?>>AppZone catalog</a>
            <?php else: ?>
                <nav class="sidebar-nav" aria-label="Workspace">
                    <a <?= $navAttrs($navActive('/dashboard')) ?> href="/dashboard">
                        <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="5" rx="1.5"/><rect x="13" y="10" width="8" height="11" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/></svg>
                        <span>Dashboard</span>
                    </a>
                    <a <?= $navAttrs($navActive('/assistant')) ?> href="/assistant" <?= $navActive('/assistant') ? '' : 'data-assistant-open' ?>>
                        <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2Z"/><path d="m19 15 .9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/></svg>
                        <span>AI Assistant</span>
                    </a>
                    <a <?= $navAttrs($navActive('/messages')) ?> href="/messages">
                        <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v9a1.5 1.5 0 0 1-1.5 1.5H9l-5 4V5.5Z"/><path d="M8 9h8M8 12h5"/></svg>
                        <span>Messages</span>
                    </a>
                    <a <?= $navAttrs($navActive('/calendar')) ?> href="/calendar">
                        <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/></svg>
                        <span>Calendar</span>
                    </a>
                    <?php $appsActive = $navActive('/appzone', ...$appPaths); ?>
                    <details class="nav-group" <?= $appsActive ? 'open' : '' ?>>
                        <summary class="nav-link <?= $appsActive ? 'active' : '' ?>">
                            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="2"/><rect x="13.5" y="3.5" width="7" height="7" rx="2"/><rect x="3.5" y="13.5" width="7" height="7" rx="2"/><path d="M17 13.5v7M13.5 17h7"/></svg>
                            <span>Apps</span>
                            <svg class="nav-caret" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                        </summary>
                        <div class="nav-sub nav-app-grid">
                            <?php foreach ($defaultApps as $appKey => $defaultApp): ?>
                                <?php $tileActive = $currentPath === $defaultApp['path']; ?>
                                <a class="app-thumb <?= $tileActive ? 'active' : '' ?>" href="<?= e($defaultApp['path']) ?>" title="<?= e($defaultApp['name']) ?>" <?= $tileActive ? 'aria-current="page"' : '' ?>>
                                    <?php $iconKey = $appKey; $iconSize = ''; require __DIR__ . '/partials/app-icon.php'; ?>
                                    <span class="app-thumb-label"><?= e($defaultApp['name']) ?></span>
                                </a>
                            <?php endforeach; ?>
                            <?php foreach ($installedApps as $app): ?>
                                <?php $appPath = (string) parse_url((string) $app['launch_url'], PHP_URL_PATH); $appActive = $appPath === $currentPath && in_array($appPath, $appPaths, true); ?>
                                <a class="app-thumb <?= $appActive ? 'active' : '' ?>" href="<?= e($app['launch_url']) ?>" title="<?= e($app['name']) ?>" <?= $appActive ? 'aria-current="page"' : '' ?>>
                                    <span class="app-icon tone-blue" aria-hidden="true"><b><?= e(mb_strtoupper(mb_substr($app['name'], 0, 1))) ?></b></span>
                                    <span class="app-thumb-label"><?= e($app['name']) ?></span>
                                </a>
                            <?php endforeach; ?>
                            <a class="app-thumb <?= $navActive('/appzone') ? 'active' : '' ?>" href="/appzone" title="Browse AppZone" <?= $navActive('/appzone') ? 'aria-current="page"' : '' ?>>
                                <span class="app-icon is-add" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
                                <span class="app-thumb-label">AppZone</span>
                            </a>
                        </div>
                    </details>
                    <div class="nav-group is-static">
                        <a <?= $navAttrs($navActive('/storage')) ?> href="/storage">
                            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3.5 7.5A2 2 0 0 1 5.5 5.5h4l2 2h7a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2v-10Z"/></svg>
                            <span>Storage</span>
                        </a>
                        <?php if ($storageDrive !== null): ?>
                            <div class="nav-sub">
                                <a class="nav-sublink" href="/storage" title="Open drive <?= e($storageDrive) ?>">
                                    <svg class="nav-drive-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="13" width="18" height="7" rx="2"/><path d="M5.5 13 8 5h8l2.5 8"/><path d="M7 16.5h.01M10.5 16.5H17"/></svg>
                                    <span class="nav-sublink-label nav-drive-name"><?= e($storageDrive) ?></span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <a <?= $navAttrs($navActive('/settings', '/billing')) ?> href="/settings">
                        <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.6 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/></svg>
                        <span>Settings</span>
                    </a>
                </nav>
            <?php endif; ?>
            <div class="sidebar-footer">
                <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['first_name'], 0, 1))) ?></span>
                <div class="account-name">
                    <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                    <span><?= e($user['email']) ?></span>
                </div>
                <form method="post" action="/logout">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button class="icon-button" type="submit" aria-label="Sign out" title="Sign out">↗</button>
                </form>
            </div>
        </aside>
        <main class="main-content">
            <div class="workspace"><?= $content ?></div>
        </main>
    </div>
    </div>
<?php else: ?>
    <header class="public-header">
        <a class="brand" href="/"><span>Garwin</span><strong>AI</strong></a>
        <nav aria-label="Account navigation">
            <?php $getStartedSize = 'button-small'; $getStartedClass = 'menu-end'; require __DIR__ . '/partials/get-started.php'; ?>
        </nav>
    </header>
    <main class="public-main"><?= $content ?></main>
    <footer class="public-footer">
        <span>© <?= gmdate('Y') ?> Garwin AI</span>
        <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>
    </footer>
<?php endif; ?>
<?php if ($user !== null && !$user['is_admin'] && $currentPath !== '/assistant'): ?>
    <button class="chat-launcher" type="button" data-assistant-open aria-controls="chat-widget" aria-expanded="false" aria-label="Open AI Assistant" title="AI Assistant">
        <svg class="chat-launcher-open" viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2Z"/><path d="m19 15 .9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/></svg>
        <svg class="chat-launcher-close" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
    </button>
    <section id="chat-widget" class="chat-widget" role="dialog" aria-label="AI Assistant" aria-modal="false" data-csrf="<?= e($csrfToken) ?>" hidden>
        <header class="chat-widget-header">
            <span class="chat-widget-icon" aria-hidden="true">✦</span>
            <div class="chat-widget-title">
                <strong>AI Assistant</strong>
                <span>Chats are saved to your account</span>
            </div>
            <button class="chat-widget-action" type="button" data-chat-new title="New chat" aria-label="Start a new chat">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </button>
            <a class="chat-widget-action" href="/assistant" title="Open full view" aria-label="Open the full AI Assistant">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-8 8M10 5H5v14h14v-5"/></svg>
            </a>
            <button class="chat-widget-action" type="button" data-chat-close title="Close" aria-label="Close AI Assistant">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
            </button>
        </header>
        <div class="chat-widget-messages" data-chat-messages aria-live="polite">
            <div class="chat-widget-welcome" data-chat-welcome>
                <span class="feature-icon" aria-hidden="true">✦</span>
                <strong>Hi <?= e($user['first_name']) ?>, what can I help with?</strong>
                <span>Ask a question, draft something, or plan your next step.</span>
            </div>
        </div>
        <form class="chat-widget-composer" method="post" action="/assistant/send" data-chat-form>
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <label class="sr-only" for="chat-widget-input">Message GarwinAI</label>
            <textarea id="chat-widget-input" name="message" rows="1" maxlength="4000" required placeholder="Message GarwinAI…"></textarea>
            <button class="chat-widget-send" type="submit" aria-label="Send message" title="Send">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h13M13 6l6 6-6 6"/></svg>
            </button>
        </form>
    </section>
<?php endif; ?>
<?php if ($user !== null && $user['is_admin']): ?>
    <script src="/assets/js/admin.js" defer></script>
<?php elseif ($user !== null): ?>
    <script src="/assets/js/workspace.js" defer></script>
<?php else: ?>
    <script src="/assets/js/public.js" defer></script>
<?php endif; ?>
</body>
</html>
