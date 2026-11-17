<section class="page-heading">
    <div>
        <p class="eyebrow">YOUR WORKSPACE</p>
        <h1>Good to see you, <?= e($user['first_name']) ?>.</h1>
        <p class="page-subtitle">Arrange your dashboard widgets and your layout will be saved automatically.</p>
    </div>
    <div class="dashboard-actions">
        <details class="create-menu">
            <summary class="create-menu-trigger" aria-label="Create new" title="Create new">
                <span aria-hidden="true">+</span>
            </summary>
            <div class="create-menu-panel">
                <p>Create new</p>
                <ul>
                    <li>AI Agent</li>
                    <li>Message</li>
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
        <span class="plan-pill"><span class="status-dot"></span> <?= e(ucfirst($account['plan_key'])) ?> plan</span>
    </div>
</section>
<?php
$layoutById = [];
foreach ($widgetLayout as $widget) {
    $layoutById[$widget['id']] = $widget['size'];
}
$widgetSize = static fn (string $id): string => e($layoutById[$id] ?? 'small');
?>
<p class="layout-save-status" id="layout-save-status" role="status" aria-live="polite"></p>
<section class="dashboard-widget-grid" id="dashboard-widgets" data-layout="<?= e(json_encode($widgetLayout, JSON_THROW_ON_ERROR)) ?>" data-csrf="<?= e($csrfToken) ?>" aria-label="Dashboard widgets">
    <article class="dashboard-widget" data-widget="overview" data-widget-size="<?= $widgetSize('overview') ?>" draggable="true">
        <header class="widget-header">
            <h2>Overview</h2>
            <div class="widget-actions">
                <button class="widget-move" type="button" data-direction="up" aria-label="Move Overview earlier">↑</button>
                <button class="widget-move" type="button" data-direction="down" aria-label="Move Overview later">↓</button>
                <button class="widget-size-toggle" type="button" aria-label="Change Overview widget size">Size</button>
            </div>
        </header>
        <div class="overview-values">
            <div><span class="detail-label">Membership</span><strong><?= e(ucfirst($account['plan_key'])) ?></strong></div>
            <div><span class="detail-label">AI budget used</span><strong>$<?= number_format((int) $account['spent_cents'] / 100, 2) ?></strong><progress class="meter" max="<?= e((string) $account['budget_cents']) ?>" value="<?= e((string) min((int) $account['budget_cents'], (int) $account['spent_cents'])) ?>"></progress></div>
            <div><span class="detail-label">Workspace</span><strong><?= e($user['user_id']) ?></strong></div>
        </div>
    </article>
    <article class="dashboard-widget" data-widget="weather" data-widget-size="<?= $widgetSize('weather') ?>" data-weather-location="<?= e((string) ($preferences['weather_location'] ?? '')) ?>" draggable="true">
        <header class="widget-header">
            <h2>Weather</h2>
            <div class="widget-actions">
                <button class="widget-move" type="button" data-direction="up" aria-label="Move Weather earlier">↑</button>
                <button class="widget-move" type="button" data-direction="down" aria-label="Move Weather later">↓</button>
                <button class="widget-size-toggle" type="button" aria-label="Change Weather widget size">Size</button>
            </div>
        </header>
        <p class="widget-value" id="weather-value"><?= trim((string) ($preferences['weather_location'] ?? '')) === '' ? 'Set a location in Settings' : 'Loading weather…' ?></p>
        <p class="widget-caption" id="weather-caption"><?= trim((string) ($preferences['weather_location'] ?? '')) === '' ? 'Weather location is not set.' : e((string) $preferences['weather_location']) ?></p>
    </article>
    <article class="dashboard-widget" data-widget="clock" data-widget-size="<?= $widgetSize('clock') ?>" data-time-format="<?= e((string) ($preferences['time_format'] ?? '24')) ?>" draggable="true">
        <header class="widget-header">
            <h2>Time</h2>
            <div class="widget-actions">
                <button class="widget-move" type="button" data-direction="up" aria-label="Move Time earlier">↑</button>
                <button class="widget-move" type="button" data-direction="down" aria-label="Move Time later">↓</button>
                <button class="widget-size-toggle" type="button" aria-label="Change Time widget size">Size</button>
            </div>
        </header>
        <p class="widget-value" id="workspace-clock"></p>
        <p class="widget-caption" id="workspace-date"></p>
    </article>
    <article class="dashboard-widget" data-widget="files" data-widget-size="<?= $widgetSize('files') ?>" draggable="true">
        <header class="widget-header">
            <h2>Files</h2>
            <div class="widget-actions">
                <button class="widget-move" type="button" data-direction="up" aria-label="Move Files earlier">↑</button>
                <button class="widget-move" type="button" data-direction="down" aria-label="Move Files later">↓</button>
                <button class="widget-size-toggle" type="button" aria-label="Change Files widget size">Size</button>
            </div>
        </header>
        <p class="widget-value"><?= e((string) $folderCount) ?> folders</p>
        <p class="widget-caption"><?= number_format((int) $account['used_bytes'] / 1073741824, 2) ?> GB of <?= number_format((int) $account['quota_bytes'] / 1073741824, 0) ?> GB used</p>
        <a class="text-link" href="/storage">Open storage →</a>
    </article>
    <article class="dashboard-widget" data-widget="messages" data-widget-size="<?= $widgetSize('messages') ?>" draggable="true">
        <header class="widget-header">
            <h2>Messages</h2>
            <div class="widget-actions">
                <button class="widget-move" type="button" data-direction="up" aria-label="Move Messages earlier">↑</button>
                <button class="widget-move" type="button" data-direction="down" aria-label="Move Messages later">↓</button>
                <button class="widget-size-toggle" type="button" aria-label="Change Messages widget size">Size</button>
            </div>
        </header>
        <p class="widget-caption">Messaging is not connected yet.</p>
    </article>
    <article class="dashboard-widget" data-widget="notifications" data-widget-size="<?= $widgetSize('notifications') ?>" draggable="true">
        <header class="widget-header">
            <h2>Notifications</h2>
            <div class="widget-actions">
                <button class="widget-move" type="button" data-direction="up" aria-label="Move Notifications earlier">↑</button>
                <button class="widget-move" type="button" data-direction="down" aria-label="Move Notifications later">↓</button>
                <button class="widget-size-toggle" type="button" aria-label="Change Notifications widget size">Size</button>
            </div>
        </header>
        <p class="widget-caption">You’re all caught up.</p>
    </article>
    <article class="dashboard-widget" data-widget="support" data-widget-size="<?= $widgetSize('support') ?>" draggable="true">
        <header class="widget-header">
            <h2>Support</h2>
            <div class="widget-actions">
                <button class="widget-move" type="button" data-direction="up" aria-label="Move Support earlier">↑</button>
                <button class="widget-move" type="button" data-direction="down" aria-label="Move Support later">↓</button>
                <button class="widget-size-toggle" type="button" aria-label="Change Support widget size">Size</button>
            </div>
        </header>
        <p class="widget-caption">Need help with your workspace?</p>
        <a class="text-link" href="mailto:<?= e($supportEmail) ?>">Contact support →</a>
    </article>
</section>
