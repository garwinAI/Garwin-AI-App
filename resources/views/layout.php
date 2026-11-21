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
$pinnedApps = [];
if ($user !== null && !$user['is_admin']) {
    try {
        $pinnedStatement = app_db()->prepare(
            'SELECT i.name, i.launch_url
             FROM appzone_installs x JOIN appzone_items i ON i.id = x.app_id
             WHERE x.user_id = :user_id AND i.is_published = 1
             ORDER BY x.installed_at, x.id LIMIT 5'
        );
        $pinnedStatement->execute(['user_id' => $user['id']]);
        $pinnedApps = $pinnedStatement->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException) {
        $pinnedApps = [];
    }
}
?>
<?php if ($user !== null): ?>
    <div class="app-shell">
        <header class="topbar">
            <a class="brand topbar-brand" href="<?= $user['is_admin'] ? '/admin' : '/dashboard' ?>" aria-label="Garwin AI home"><span>Garwin</span><strong>AI</strong></a>
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
                    <a class="workspace-shortcut <?= $currentPath === '/assistant' ? 'active' : '' ?>" href="/assistant" aria-label="AI Assistant" title="AI Assistant" <?= $currentPath === '/assistant' ? 'aria-current="page"' : '' ?>>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2 1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2Z"/><path d="m19 15 .9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/></svg>
                    </a>
                    <a class="workspace-shortcut <?= $currentPath === '/wordbase' ? 'active' : '' ?>" href="/wordbase" aria-label="Wordbase" title="Wordbase" <?= $currentPath === '/wordbase' ? 'aria-current="page"' : '' ?>>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4.5h14a1.5 1.5 0 0 1 1.5 1.5v12A1.5 1.5 0 0 1 19 19.5H5A1.5 1.5 0 0 1 3.5 18V6A1.5 1.5 0 0 1 5 4.5Zm0 3h14M8 12h8M8 8h4"/></svg>
                    </a>
                    <a class="workspace-shortcut <?= $currentPath === '/storage' ? 'active' : '' ?>" href="/storage" aria-label="Storage" title="Storage" <?= $currentPath === '/storage' ? 'aria-current="page"' : '' ?>>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v5H4zM4 12h16v7H4z"/><path d="M8 7.5h.01M8 15.5h.01"/></svg>
                    </a>
                </nav>
            <?php else: ?>
                <span class="topbar-note">Super Admin</span>
            <?php endif; ?>
            <a class="support-link" href="mailto:<?= e($supportEmail) ?>">Help &amp; support</a>
            <?php if (!$user['is_admin']): ?>
                <div class="pinned-apps" aria-label="Pinned apps">
                    <span class="pinned-apps-label">Pinned</span>
                    <div class="pinned-app-slots">
                        <?php foreach ($pinnedApps as $app): ?>
                            <a class="pinned-app-link" href="<?= e($app['launch_url']) ?>" target="_blank" rel="noopener noreferrer" title="<?= e($app['name']) ?>" aria-label="<?= e($app['name']) ?>">
                                <?= e(mb_strtoupper(mb_substr($app['name'], 0, 1))) ?>
                            </a>
                        <?php endforeach; ?>
                        <?php for ($slot = count($pinnedApps) + 1; $slot <= 5; $slot++): ?>
                            <span class="pinned-app-slot" title="Empty pinned app slot <?= $slot ?> of 5" aria-hidden="true">+</span>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endif; ?>
        </header>
        <aside class="sidebar" aria-label="Main navigation">
            <?php if ($user['is_admin']): ?>
                <p class="nav-label">ADMINISTRATION</p>
                <a class="nav-link" href="/admin">Admin dashboard</a>
                <a class="nav-link" href="/admin/settings?tab=launch">Admin settings</a>
                <a class="nav-link" href="/admin/settings?tab=demo-users">Demo Users</a>
                <a class="nav-link <?= $currentPath === '/admin/appzone' ? 'active' : '' ?>" href="/admin/appzone" <?= $currentPath === '/admin/appzone' ? 'aria-current="page"' : '' ?>>AppZone catalog</a>
            <?php else: ?>
                <p class="nav-label">WORKSPACE</p>
                <a class="nav-link <?= $currentPath === '/dashboard' ? 'active' : '' ?>" href="/dashboard" <?= $currentPath === '/dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
                <a class="nav-link <?= $currentPath === '/assistant' ? 'active' : '' ?>" href="/assistant" <?= $currentPath === '/assistant' ? 'aria-current="page"' : '' ?>>AI Assistant</a>
                <p class="nav-label">APPS</p>
                <a class="nav-link <?= $currentPath === '/appzone' ? 'active' : '' ?>" href="/appzone" <?= $currentPath === '/appzone' ? 'aria-current="page"' : '' ?>>AppZone</a>
                <a class="nav-link <?= $currentPath === '/developer' ? 'active' : '' ?>" href="/developer" <?= $currentPath === '/developer' ? 'aria-current="page"' : '' ?>>Developer App</a>
                <a class="nav-link <?= $currentPath === '/wordbase' ? 'active' : '' ?>" href="/wordbase" <?= $currentPath === '/wordbase' ? 'aria-current="page"' : '' ?>>Wordbase</a>
                <a class="nav-link <?= $currentPath === '/calendar' ? 'active' : '' ?>" href="/calendar" <?= $currentPath === '/calendar' ? 'aria-current="page"' : '' ?>>Calendar</a>
                <a class="nav-link <?= $currentPath === '/storage' ? 'active' : '' ?>" href="/storage" <?= $currentPath === '/storage' ? 'aria-current="page"' : '' ?>>Storage</a>
                <a class="nav-link <?= $currentPath === '/billing' ? 'active' : '' ?>" href="/billing" <?= $currentPath === '/billing' ? 'aria-current="page"' : '' ?>>Billing &amp; membership</a>
                <a class="nav-link <?= $currentPath === '/settings' ? 'active' : '' ?>" href="/settings" <?= $currentPath === '/settings' ? 'aria-current="page"' : '' ?>>Settings</a>
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
<?php else: ?>
    <header class="public-header">
        <a class="brand" href="/"><span>Garwin</span><strong>AI</strong></a>
        <nav aria-label="Account navigation">
            <a class="text-link" href="/login">Login</a>
            <a class="button button-small" href="/register">Get Started FREE</a>
        </nav>
    </header>
    <main class="public-main"><?= $content ?></main>
    <footer class="public-footer">
        <span>© <?= gmdate('Y') ?> Garwin AI</span>
        <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>
    </footer>
<?php endif; ?>
<?php if ($user !== null && $user['is_admin']): ?>
    <script src="/assets/js/admin.js" defer></script>
<?php elseif ($user !== null): ?>
    <script src="/assets/js/workspace.js" defer></script>
<?php endif; ?>
</body>
</html>
