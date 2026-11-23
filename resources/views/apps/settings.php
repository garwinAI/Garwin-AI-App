<?php
$settingsIcons = require __DIR__ . '/../partials/icons.php';
$settingsIcon = static fn (string $name): string => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">' . ($settingsIcons[$name] ?? $settingsIcons['app']) . '</svg>';
$return = '/apps/settings?app=' . rawurlencode($app['key']);
$pinFull = !$app['pinned'] && $pinCount >= App\Services\NavigationService::MAX_PINS;
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">APP SETTINGS</p>
        <h1><?= e($app['label']) ?></h1>
        <p class="page-subtitle"><?= e($app['description']) ?></p>
    </div>
    <?php if ($app['active']): ?><a class="button" href="<?= e($app['href']) ?>">GO</a><?php endif; ?>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>

<section class="settings-card app-settings">
    <div class="app-settings-row">
        <div>
            <h2>Active</h2>
            <p class="page-subtitle">When an app is off, it leaves your sidebar and top bar until you turn it back on.</p>
        </div>
        <form method="post" action="/apps/toggle">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="key" value="<?= e($app['key']) ?>">
            <input type="hidden" name="return" value="<?= e($return) ?>">
            <button class="app-switch <?= $app['active'] ? 'on' : '' ?>" type="submit" role="switch" aria-checked="<?= $app['active'] ? 'true' : 'false' ?>" aria-label="<?= $app['active'] ? 'Deactivate' : 'Activate' ?> <?= e($app['label']) ?>"><span></span></button>
        </form>
    </div>
    <?php if ($app['pinnable']): ?>
        <div class="app-settings-row">
            <div>
                <h2>Pinned to the top bar</h2>
                <p class="page-subtitle"><?= $pinFull ? 'The top bar is full (5 apps). Unpin another app first.' : 'Pinned apps sit on the right of the top bar. Up to 5.' ?></p>
            </div>
            <form method="post" action="/pins/toggle">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="key" value="<?= e($app['key']) ?>">
                <input type="hidden" name="return" value="<?= e($return) ?>">
                <button class="app-switch <?= $app['pinned'] ? 'on' : '' ?>" type="submit" role="switch" aria-checked="<?= $app['pinned'] ? 'true' : 'false' ?>" aria-label="Pin <?= e($app['label']) ?> to the top bar" <?= $pinFull || !$app['active'] ? 'disabled' : '' ?>><span></span></button>
            </form>
        </div>
    <?php elseif (in_array($app['key'], App\Services\NavigationService::FIXED_TOP_BAR, true)): ?>
        <div class="app-settings-row">
            <div>
                <h2>On the top bar</h2>
                <p class="page-subtitle"><?= e($app['label']) ?> always has its own icon on the left of the top bar while it's on.</p>
            </div>
        </div>
    <?php endif; ?>
</section>
