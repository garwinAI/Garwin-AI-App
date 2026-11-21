<?php
$dashboardIcons = require __DIR__ . '/../partials/icons.php';
$dashboardIcons += [
    'weather' => '<circle cx="9" cy="9" r="3.5"/><path d="M9 2.5v1.5M3.5 9H2M4.8 4.8l1 1M13.2 4.8l-1 1"/><path d="M8 20h9a3.5 3.5 0 0 0 0-7 5 5 0 0 0-9.6 1.5A2.8 2.8 0 0 0 8 20Z"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'bell' => '<path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z"/><path d="M10 20.5a2 2 0 0 0 4 0"/>',
    'overview' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'up' => '<path d="m6 15 6-6 6 6"/>',
    'down' => '<path d="m6 9 6 6 6-6"/>',
    'resize' => '<path d="M14 4h6v6M10 20H4v-6M20 4l-7 7M4 20l7-7"/>',
];
$dashIcon = static fn (string $name, string $class = 'widget-icon'): string => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true">' . ($dashboardIcons[$name] ?? '') . '</svg>';

$layoutById = [];
foreach ($widgetLayout as $widget) {
    $layoutById[$widget['id']] = $widget['size'];
}
$widgetSize = static fn (string $id): string => e($layoutById[$id] ?? 'small');
$widgetHeader = static fn (string $title, string $iconName): string => '<header class="widget-header">'
    . '<h2>' . $dashIcon($iconName) . e($title) . '</h2>'
    . '<div class="widget-actions">'
    . '<button class="widget-move" type="button" data-direction="up" aria-label="Move ' . e($title) . ' earlier" title="Move earlier">' . $dashIcon('up', 'widget-action-icon') . '</button>'
    . '<button class="widget-move" type="button" data-direction="down" aria-label="Move ' . e($title) . ' later" title="Move later">' . $dashIcon('down', 'widget-action-icon') . '</button>'
    . '<button class="widget-size-toggle" type="button" aria-label="Change ' . e($title) . ' widget size" title="Change size">' . $dashIcon('resize', 'widget-action-icon') . '</button>'
    . '</div></header>';

$quotaBytes = max(1, (int) $account['quota_bytes']);
$usedBytes = max(0, (int) $account['used_bytes']);
$storagePercent = min(100, $usedBytes / $quotaBytes * 100);
$formatBytes = static function (int $bytes): string {
    if ($bytes === 0) {
        return '0 GB';
    }
    if ($bytes >= 1073741824) {
        return rtrim(rtrim(number_format($bytes / 1073741824, 2), '0'), '.') . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }

    return $bytes . ' B';
};
$weatherLocation = trim((string) ($preferences['weather_location'] ?? ''));
?>
<section class="dashboard-hero">
    <div>
        <p class="eyebrow">YOUR WORKSPACE</p>
        <h1 data-greeting data-first-name="<?= e($user['first_name']) ?>">Welcome back, <?= e($user['first_name']) ?>.</h1>
        <p class="page-subtitle" data-today>Here's what's happening in your workspace.</p>
    </div>
    <div class="dashboard-actions">
        <span class="plan-pill"><span class="status-dot"></span> <?= e($account['plan_name']) ?> plan</span>
        <details class="create-menu">
            <summary class="create-menu-trigger" aria-label="Create new" title="Create new">
                <span aria-hidden="true">+</span>
            </summary>
            <div class="create-menu-panel">
                <p>Create new</p>
                <ul>
                    <li>AI Agent</li>
                    <li><a href="/messenger/compose">Message</a></li>
                    <li>Chat</li>
                    <li>App</li>
                    <li>Project</li>
                    <li>File</li>
                    <li>Folder</li>
                    <li><a href="/calendar">Calendar</a></li>
                    <li><a href="/calendar#event-form">Event</a></li>
                </ul>
            </div>
        </details>
    </div>
</section>

<p class="layout-save-status" id="layout-save-status" role="status" aria-live="polite"></p>
<section class="dashboard-widget-grid" id="dashboard-widgets" data-layout="<?= e(json_encode($widgetLayout, JSON_THROW_ON_ERROR)) ?>" data-csrf="<?= e($csrfToken) ?>" aria-label="Dashboard widgets">
    <article class="dashboard-widget widget-overview" data-widget="overview" data-widget-size="<?= $widgetSize('overview') ?>" draggable="true">
        <?= $widgetHeader('Overview', 'overview') ?>
        <div class="overview-grid">
            <div class="overview-stat">
                <span class="detail-label">Membership</span>
                <strong><?= e($account['plan_name']) ?></strong>
                <a class="overview-link" href="/billing">Manage plan →</a>
            </div>
            <div class="overview-stat overview-storage">
                <span class="detail-label">Storage</span>
                <strong><?= e($formatBytes($usedBytes)) ?> <span class="overview-of">of <?= e($formatBytes($quotaBytes)) ?></span></strong>
                <progress class="meter <?= $storagePercent >= 90 ? 'meter-warning' : '' ?>" max="100" value="<?= e(number_format($storagePercent, 2, '.', '')) ?>" aria-label="Storage used"></progress>
                <span class="overview-caption"><?= $storagePercent > 0 && $storagePercent < 1 ? 'Less than 1' : e(number_format($storagePercent, 0)) ?>% used · <?= e($formatBytes(max(0, $quotaBytes - $usedBytes))) ?> free</span>
            </div>
            <div class="overview-stat">
                <span class="detail-label">User ID</span>
                <strong class="overview-mono"><?= e($user['user_id']) ?></strong>
                <span class="overview-caption"><?= e((string) $folderCount) ?> folders in your drive</span>
            </div>
        </div>
    </article>

    <article class="dashboard-widget widget-weather" data-widget="weather" data-widget-size="<?= $widgetSize('weather') ?>" data-weather-location="<?= e($weatherLocation) ?>" draggable="true">
        <?= $widgetHeader('Weather', 'weather') ?>
        <div class="weather-now">
            <span class="weather-icon" id="weather-icon" aria-hidden="true"></span>
            <div>
                <p class="weather-temp" id="weather-value">—</p>
                <p class="weather-condition" id="weather-condition">Finding your weather…</p>
            </div>
        </div>
        <p class="weather-place" id="weather-caption"><?= $weatherLocation !== '' ? e($weatherLocation) : 'Locating…' ?></p>
        <ol class="weather-forecast" id="weather-forecast" aria-label="Forecast"></ol>
    </article>

    <article class="dashboard-widget widget-clock" data-widget="clock" data-widget-size="<?= $widgetSize('clock') ?>" data-time-format="<?= e((string) ($preferences['time_format'] ?? '24')) ?>" draggable="true">
        <?= $widgetHeader('Time', 'clock') ?>
        <p class="clock-time" id="workspace-clock"></p>
        <p class="widget-caption" id="workspace-date"></p>
        <p class="widget-caption clock-zone" id="workspace-zone"></p>
    </article>

    <article class="dashboard-widget" data-widget="files" data-widget-size="<?= $widgetSize('files') ?>" draggable="true">
        <?= $widgetHeader('Files', 'drive') ?>
        <p class="widget-value"><?= e((string) $folderCount) ?> <span>folders</span></p>
        <progress class="meter" max="100" value="<?= e(number_format($storagePercent, 2, '.', '')) ?>" aria-label="Storage used"></progress>
        <p class="widget-caption"><?= e($formatBytes($usedBytes)) ?> of <?= e($formatBytes($quotaBytes)) ?> used</p>
        <a class="widget-link" href="/storage">Open storage →</a>
    </article>

    <article class="dashboard-widget" data-widget="messages" data-widget-size="<?= $widgetSize('messages') ?>" draggable="true">
        <?= $widgetHeader('Messages', 'messenger') ?>
        <?php if ($unreadMessages === []): ?>
            <p class="widget-value">0 <span>new</span></p>
            <p class="widget-caption">No new messages.</p>
        <?php else: ?>
            <p class="widget-value"><?= e((string) $unreadCount) ?> <span>new</span></p>
            <ul class="widget-list">
                <?php foreach ($unreadMessages as $message): ?>
                    <li><a href="/messenger/message?id=<?= e((string) $message['id']) ?>">
                        <strong><?= e(trim($message['first_name'] . ' ' . $message['last_name'])) ?></strong>
                        <span><?php if ((int) $message['is_priority'] === 1): ?><em class="messenger-priority">Priority</em><?php endif; ?><?= e($message['subject']) ?></span>
                    </a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="widget-links">
            <a class="widget-link" href="/messenger">Open Messenger →</a>
            <a class="widget-link" href="/messenger/compose">Compose</a>
        </div>
    </article>

    <article class="dashboard-widget" data-widget="notifications" data-widget-size="<?= $widgetSize('notifications') ?>" draggable="true">
        <?= $widgetHeader('Notifications', 'bell') ?>
        <p class="widget-empty">You're all caught up.</p>
    </article>

    <article class="dashboard-widget" data-widget="support" data-widget-size="<?= $widgetSize('support') ?>" draggable="true">
        <?= $widgetHeader('Support', 'help') ?>
        <p class="widget-caption">Questions about your workspace or plan? Our team is here to help.</p>
        <a class="widget-link" href="mailto:<?= e($supportEmail) ?>">Contact support →</a>
    </article>
</section>
