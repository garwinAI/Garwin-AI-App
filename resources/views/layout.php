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
<?php if ($user !== null): ?>
<?php
$currentPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$currentUri = (string) ($_SERVER['REQUEST_URI'] ?? '/dashboard');
$currentView = is_string($_GET['view'] ?? null) ? $_GET['view'] : 'new';
$iconPaths = require __DIR__ . '/partials/icons.php';
$icon = static fn (string $name): string => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">' . ($iconPaths[$name] ?? $iconPaths['app']) . '</svg>';
$isActive = static fn (string $href): bool => $currentPath === $href || str_starts_with($currentPath, rtrim($href, '/') . '/');

$navigation = null;
if (!$user['is_admin']) {
    $navigation = (new App\Services\NavigationService(app_db()))->forUser($user);
}

/** One sidebar destination: icon + label, with an optional pin toggle and expand button. */
$navRow = static function (string $href, string $label, string $iconName, ?string $pinKey = null, ?string $treeId = null, bool $active = false) use ($icon, $navigation, $csrfToken, $currentUri): string {
    $html = '<div class="nav-row">';
    $html .= '<a class="nav-link' . ($active ? ' active' : '') . '" href="' . e($href) . '" title="' . e($label) . '"' . ($active ? ' aria-current="page"' : '') . '>'
        . $icon($iconName) . '<span class="nav-text">' . e($label) . '</span></a>';
    if ($treeId !== null) {
        $html .= '<button class="nav-expand" type="button" data-tree-toggle aria-expanded="true" aria-controls="' . e($treeId) . '" aria-label="Show or hide ' . e($label) . ' items">' . $icon('chevron') . '</button>';
    }
    if ($pinKey !== null && $navigation !== null) {
        $pinned = in_array($pinKey, $navigation['pins'], true);
        $action = $pinned ? 'Unpin ' . $label . ' from the top bar' : 'Pin ' . $label . ' to the top bar';
        $html .= '<form class="nav-pin-form" method="post" action="/pins/toggle">'
            . '<input type="hidden" name="_token" value="' . e($csrfToken) . '">'
            . '<input type="hidden" name="key" value="' . e($pinKey) . '">'
            . '<input type="hidden" name="return" value="' . e($currentUri) . '">'
            . '<button class="nav-pin' . ($pinned ? ' pinned' : '') . '" type="submit" title="' . e($action) . '" aria-label="' . e($action) . '" aria-pressed="' . ($pinned ? 'true' : 'false') . '">' . $icon('pin') . '</button>'
            . '</form>';
    }

    return $html . '</div>';
};
$item = static function (string $key) use ($navigation, $navRow, $isActive): string {
    $entry = $navigation['items'][$key];

    return $navRow($entry['href'], $entry['label'], $entry['icon'], $key, null, $isActive($entry['href']));
};
?>
    <div class="app-shell" data-app-shell>
        <header class="topbar">
            <button class="topbar-toggle" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="true" aria-label="Collapse or expand the sidebar" title="Toggle sidebar"><?= $icon('sidebar') ?></button>
            <a class="brand topbar-brand" href="<?= $user['is_admin'] ? '/admin' : '/dashboard' ?>" aria-label="Garwin AI home"><span>Garwin</span><strong>AI</strong></a>
            <?php if ($navigation !== null): ?>
                <nav class="topbar-pins" aria-label="Pinned apps">
                    <?php foreach ($navigation['pins'] as $pinKey): $pin = $navigation['items'][$pinKey]; $pinActive = $isActive($pin['href']); ?>
                        <a class="topbar-pin <?= $pinActive ? 'active' : '' ?>" href="<?= e($pin['href']) ?>" title="<?= e($pin['label']) ?>" aria-label="<?= e($pin['label']) ?>" <?= $pinActive ? 'aria-current="page"' : '' ?>><?= $icon($pin['icon']) ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php else: ?>
                <span class="topbar-note">Super Admin</span>
            <?php endif; ?>
            <a class="support-link" href="mailto:<?= e($supportEmail) ?>">Help &amp; support</a>
        </header>

        <aside class="sidebar" id="app-sidebar" aria-label="Main navigation">
            <nav class="sidebar-nav">
                <?php if ($navigation === null): ?>
                    <div class="nav-group" data-nav-group="administration">
                        <button class="nav-section-toggle" type="button" data-group-toggle aria-expanded="true"><span>Administration</span><?= $icon('chevron') ?></button>
                        <div class="nav-group-items">
                            <?= $navRow('/admin', 'Admin dashboard', 'admin', null, null, $currentPath === '/admin') ?>
                            <?= $navRow('/admin/settings?tab=launch', 'Admin settings', 'settings', null, null, $currentPath === '/admin/settings' && ($_GET['tab'] ?? 'launch') !== 'demo-users') ?>
                            <?= $navRow('/admin/settings?tab=demo-users', 'Demo Users', 'users', null, null, $currentPath === '/admin/settings' && ($_GET['tab'] ?? '') === 'demo-users') ?>
                            <?= $navRow('/admin/appzone', 'AppZone catalog', 'appzone', null, null, $currentPath === '/admin/appzone') ?>
                        </div>
                    </div>
                <?php else: $counts = $navigation['messageCounts']; ?>
                    <div class="nav-group" data-nav-group="workspace">
                        <button class="nav-section-toggle" type="button" data-group-toggle aria-expanded="true"><span>Workspace</span><?= $icon('chevron') ?></button>
                        <div class="nav-group-items">
                            <?= $item('dashboard') ?>
                            <?= $item('assistant') ?>
                            <div class="nav-tree" data-nav-tree="storage">
                                <?= $navRow('/storage', 'Storage', 'storage', 'storage', 'nav-storage-items', $isActive('/storage')) ?>
                                <div class="nav-children" id="nav-storage-items">
                                    <a class="nav-sublink" href="/storage" title="<?= e($navigation['driveLabel']) ?>"><?= $icon('drive') ?><span class="nav-text"><?= e($navigation['driveLabel']) ?></span></a>
                                </div>
                            </div>
                            <?= $item('calendar') ?>
                            <div class="nav-tree" data-nav-tree="messenger">
                                <?= $navRow('/messenger', 'Messenger', 'messenger', 'messenger', 'nav-messenger-items', false) ?>
                                <div class="nav-children" id="nav-messenger-items">
                                    <?php foreach (['new' => ['New', 'mail-new'], 'read' => ['Read', 'mail-read'], 'priority' => ['Priority', 'priority']] as $viewKey => [$viewLabel, $viewIcon]): $subActive = $currentPath === '/messenger' && $currentView === $viewKey; ?>
                                        <a class="nav-sublink <?= $subActive ? 'active' : '' ?>" href="/messenger?view=<?= $viewKey ?>" <?= $subActive ? 'aria-current="page"' : '' ?>><?= $icon($viewIcon) ?><span class="nav-text"><?= $viewLabel ?></span><span class="nav-count <?= $viewKey === 'new' && $counts['new'] > 0 ? 'highlight' : '' ?>"><?= $counts[$viewKey] ?></span></a>
                                    <?php endforeach; ?>
                                    <a class="nav-sublink <?= $currentPath === '/messenger/compose' ? 'active' : '' ?>" href="/messenger/compose"><?= $icon('compose') ?><span class="nav-text">Compose New Message</span></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="nav-group" data-nav-group="apps">
                        <button class="nav-section-toggle" type="button" data-group-toggle aria-expanded="true"><span>Apps</span><?= $icon('chevron') ?></button>
                        <div class="nav-group-items">
                            <?= $item('wordbase') ?>
                            <?= $item('spreadsheets') ?>
                            <?= $item('codebase') ?>
                            <?php if (isset($navigation['items']['developer'])): ?><?= $item('developer') ?><?php endif; ?>
                            <?php foreach ($navigation['otherApps'] as $app): ?>
                                <?= $navRow($app['launch_url'], $app['name'], 'app') ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="nav-standalone">
                        <?= $item('appzone') ?>
                    </div>
                    <div class="nav-bottom">
                        <?= $navRow('/billing', 'Billing & membership', 'billing', null, null, $isActive('/billing')) ?>
                        <?= $navRow('/settings', 'Settings', 'settings', null, null, $isActive('/settings')) ?>
                        <div class="nav-mobile-only"><?= $navRow('mailto:' . $supportEmail, 'Help & support', 'help') ?></div>
                    </div>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <span class="avatar" title="<?= e($user['first_name'] . ' ' . $user['last_name']) ?>"><?= e(mb_strtoupper(mb_substr($user['first_name'], 0, 1))) ?></span>
                <div class="account-name">
                    <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                    <span><?= e($user['email']) ?></span>
                </div>
                <form method="post" action="/logout">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button class="icon-button" type="submit" aria-label="Sign out" title="Sign out"><?= $icon('logout') ?></button>
                </form>
            </div>
            <div class="sidebar-resizer" data-sidebar-resizer role="separator" aria-orientation="vertical" aria-label="Resize sidebar" tabindex="0" title="Drag to resize. Double-click to reset."></div>
        </aside>
        <div class="sidebar-backdrop" data-sidebar-backdrop></div>

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
<?php if ($user !== null): ?>
    <script src="/assets/js/shell.js" defer></script>
<?php endif; ?>
<?php if ($user !== null && $user['is_admin']): ?>
    <script src="/assets/js/admin.js" defer></script>
<?php elseif ($user !== null): ?>
    <script src="/assets/js/workspace.js" defer></script>
<?php endif; ?>
</body>
</html>
