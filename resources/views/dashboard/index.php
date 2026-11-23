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
    $layoutById[$widget['id']] = $widget;
}
$widgetSize = static fn (string $id): string => e($layoutById[$id]['size'] ?? (in_array($id, ['overview', 'messages'], true) ? 'wide' : 'small'));
$widgetTitle = static fn (string $id): string => ($layoutById[$id]['title'] ?? '') !== '' ? $layoutById[$id]['title'] : $widgetCatalog[$id][0];
$widgetHeader = static function (string $id) use ($widgetTitle, $widgetCatalog, $dashIcon): string {
    $title = $widgetTitle($id);

    return '<header class="widget-header">'
        . '<h2>' . icon_tile($widgetCatalog[$id][1]) . '<span class="widget-title" data-widget-title data-default-title="' . e($widgetCatalog[$id][0]) . '">' . e($title) . '</span></h2>'
        . '<div class="widget-actions">'
        . '<button class="widget-rename" type="button" aria-label="Rename ' . e($title) . '" title="Rename">' . $dashIcon('pencil', 'widget-action-icon') . '</button>'
        . '<button class="widget-move" type="button" data-direction="up" aria-label="Move ' . e($title) . ' earlier" title="Move earlier">' . $dashIcon('up', 'widget-action-icon') . '</button>'
        . '<button class="widget-move" type="button" data-direction="down" aria-label="Move ' . e($title) . ' later" title="Move later">' . $dashIcon('down', 'widget-action-icon') . '</button>'
        . '<button class="widget-size-toggle" type="button" aria-label="Change ' . e($title) . ' widget size" title="Change size">' . $dashIcon('resize', 'widget-action-icon') . '</button>'
        . '<button class="widget-remove" type="button" aria-label="Remove ' . e($title) . ' from this page" title="Remove from page">' . $dashIcon('close', 'widget-action-icon') . '</button>'
        . '</div></header>';
};
$widgetAttrs = static fn (string $id): string => 'data-widget="' . e($id) . '" data-widget-size="' . $widgetSize($id) . '" data-widget-custom-title="' . e($layoutById[$id]['title'] ?? '') . '" draggable="true"';

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
<?php $toolbarWidgets = $widgetCatalog; require __DIR__ . '/../partials/workspace-toolbar.php'; ?>



<p class="layout-save-status" id="layout-save-status" role="status" aria-live="polite"></p>
<section class="dashboard-widget-grid" id="dashboard-widgets" data-layout="<?= e(json_encode($widgetLayout, JSON_THROW_ON_ERROR)) ?>" data-csrf="<?= e($csrfToken) ?>" aria-label="Dashboard widgets">
    <article class="dashboard-widget widget-welcome" <?= $widgetAttrs('welcome') ?>>
        <?= $widgetHeader('welcome') ?>
        <div class="welcome-body">
            <div>
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
        </div>
    </article>

    <article class="dashboard-widget widget-overview" <?= $widgetAttrs('overview') ?>>
        <?= $widgetHeader('overview') ?>
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

    <article class="dashboard-widget widget-notify" <?= $widgetAttrs('notify') ?>>
        <?= $widgetHeader('notify') ?>
        <ul class="notify-list">
            <?php foreach ([
                ['messages', 'Messages', 'mail-new', '/messenger?view=new'],
                ['notifications', 'Notifications', 'bell', '/dashboard#notifications'],
                ['support', 'Support', 'headset', 'mailto:' . $supportEmail],
            ] as [$notifyKey, $notifyLabel, $notifyIcon, $notifyHref]): $notifyCount = (int) $notifyCounts[$notifyKey]; ?>
                <li>
                    <a href="<?= e($notifyHref) ?>">
                        <?= icon_tile($notifyIcon) ?>
                        <span class="notify-label"><?= e($notifyLabel) ?></span>
                        <?php if ($notifyCount > 0): ?>
                            <span class="notify-badge" aria-label="<?= e((string) $notifyCount) ?> unread"><?= $notifyCount > 99 ? '99+' : e((string) $notifyCount) ?></span>
                        <?php else: ?>
                            <span class="notify-none">No unread</span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </article>

    <article class="dashboard-widget widget-weather" <?= $widgetAttrs('weather') ?> data-weather-location="<?= e($weatherLocation) ?>">
        <?= $widgetHeader('weather') ?>
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

    <article class="dashboard-widget widget-clock" <?= $widgetAttrs('clock') ?> data-time-format="<?= e((string) ($preferences['time_format'] ?? '12')) ?>">
        <?= $widgetHeader('clock') ?>
        <p class="clock-time" id="workspace-clock"></p>
        <p class="widget-caption" id="workspace-date"></p>
        <p class="widget-caption clock-zone" id="workspace-zone"></p>
    </article>

    <article class="dashboard-widget" <?= $widgetAttrs('files') ?>>
        <?= $widgetHeader('files') ?>
        <p class="widget-value"><?= e((string) $folderCount) ?> <span>folders</span></p>
        <progress class="meter" max="100" value="<?= e(number_format($storagePercent, 2, '.', '')) ?>" aria-label="Storage used"></progress>
        <p class="widget-caption"><?= e($formatBytes($usedBytes)) ?> of <?= e($formatBytes($quotaBytes)) ?> used</p>
        <a class="widget-link" href="/storage">Open storage →</a>
    </article>

    <article class="dashboard-widget" <?= $widgetAttrs('messages') ?>>
        <?= $widgetHeader('messages') ?>
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

    <article class="dashboard-widget" <?= $widgetAttrs('notifications') ?>>
        <?= $widgetHeader('notifications') ?>
        <p class="widget-empty">You're all caught up.</p>
    </article>

    <article class="dashboard-widget" <?= $widgetAttrs('support') ?>>
        <?= $widgetHeader('support') ?>
        <p class="widget-caption">Questions about your workspace or plan? Our team is here to help.</p>
        <a class="widget-link" href="mailto:<?= e($supportEmail) ?>">Contact support →</a>
    </article>

    <?php foreach ($widgetLayout as $window): if (!isset($window['kind'])) { continue; } $windowId = $window['id']; ?>
        <article class="dashboard-widget widget-window" id="<?= e($windowId) ?>" data-widget="<?= e($windowId) ?>" data-widget-size="<?= e($window['size']) ?>" data-widget-custom-title="<?= e($window['title']) ?>" data-window-kind="<?= e($window['kind']) ?>" <?= $window['kind'] === 'page' ? 'data-page-id="' . (int) $window['page_id'] . '"' : '' ?> draggable="true">
            <?php
            $windowPage = $window['kind'] === 'page' ? ($windowPages[$window['page_id']] ?? null) : null;
            $windowIcon = $window['kind'] === 'note' ? 'note' : (App\Services\WorkspacePageService::TYPES[$windowPage['page_type'] ?? 'doc']['icon'] ?? 'pages');
            ?>
            <header class="widget-header">
                <h2><?= icon_tile($windowIcon) ?><span class="widget-title" data-widget-title data-default-title="<?= e($window['kind'] === 'note' ? 'Note' : 'Window') ?>"><?= e($window['title']) ?></span></h2>
                <div class="widget-actions">
                    <button class="widget-rename" type="button" aria-label="Rename <?= e($window['title']) ?>" title="Rename"><?= $dashIcon('pencil', 'widget-action-icon') ?></button>
                    <button class="widget-move" type="button" data-direction="up" aria-label="Move <?= e($window['title']) ?> earlier" title="Move earlier"><?= $dashIcon('up', 'widget-action-icon') ?></button>
                    <button class="widget-move" type="button" data-direction="down" aria-label="Move <?= e($window['title']) ?> later" title="Move later"><?= $dashIcon('down', 'widget-action-icon') ?></button>
                    <button class="widget-size-toggle" type="button" aria-label="Change <?= e($window['title']) ?> size" title="Change size"><?= $dashIcon('resize', 'widget-action-icon') ?></button>
                    <button class="widget-remove" type="button" aria-label="Delete the <?= e($window['title']) ?> window" title="Delete window"><?= $dashIcon('close', 'widget-action-icon') ?></button>
                </div>
            </header>
            <?php if ($window['kind'] === 'note'): ?>
                <textarea class="window-note" data-window-note maxlength="5000" placeholder="Write a note…" aria-label="Note"><?= e($window['note']) ?></textarea>
            <?php elseif ($windowPage === null): ?>
                <p class="widget-empty">This page was deleted. Remove the window, or add another from the ⋮ menu.</p>
            <?php elseif ($windowPage['page_type'] === 'doc'): ?>
                <p class="window-doc"><?= nl2br(e(mb_strimwidth((string) $windowPage['content'], 0, 600, '…'))) ?: '<span class="widget-caption">Empty doc.</span>' ?></p>
                <a class="widget-link" href="/pages/view?id=<?= (int) $windowPage['id'] ?>">Open doc →</a>
            <?php elseif ($windowPage['page_type'] === 'sheet'): $sheetCells = json_decode((string) $windowPage['content'], true)['cells'] ?? []; ?>
                <div class="window-sheet-scroll"><table class="window-sheet">
                    <?php for ($r = 1; $r <= 6; $r++): ?><tr><?php for ($c = 0; $c < 5; $c++): $cell = (string) ($sheetCells[chr(65 + $c) . $r] ?? ''); ?><td class="<?= str_starts_with($cell, '=') ? 'is-formula' : '' ?>"><?= e(str_starts_with($cell, '=') ? 'ƒ' : $cell) ?></td><?php endfor; ?></tr><?php endfor; ?>
                </table></div>
                <a class="widget-link" href="/pages/view?id=<?= (int) $windowPage['id'] ?>">Open spreadsheet →</a>
            <?php else: $calendarEvents = json_decode((string) $windowPage['content'], true)['events'] ?? []; $upcoming = array_filter($calendarEvents, static fn ($items, $day): bool => $day >= gmdate('Y-m-d', time() - 86400), ARRAY_FILTER_USE_BOTH); ?>
                <?php if ($upcoming === []): ?>
                    <p class="widget-empty">No upcoming events.</p>
                <?php else: ?>
                    <ul class="window-events">
                        <?php foreach (array_slice($upcoming, 0, 5, true) as $day => $items): ?>
                            <li><strong><?= e(date('D, M j', strtotime($day))) ?></strong><span><?= e(implode(' · ', $items)) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a class="widget-link" href="/pages/view?id=<?= (int) $windowPage['id'] ?>">Open calendar →</a>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<div class="widget-tray" id="widget-tray" hidden></div>
