<?php
/**
 * Page heading for a built-in app.
 *
 * @var string $headingApp      Built-in app key.
 * @var string $headingSubtitle
 */
$headingMeta = App\Support\BuiltInApps::get($headingApp);
?>
<section class="page-heading app-heading">
    <div class="app-heading-title">
        <?php $iconKey = $headingApp; $iconSize = 'is-large'; require __DIR__ . '/app-icon.php'; ?>
        <div>
            <p class="eyebrow">APPS</p>
            <h1><?= e($headingMeta['name']) ?></h1>
            <p class="page-subtitle"><?= e($headingSubtitle) ?></p>
        </div>
    </div>
    <?= $headingActions ?? '' ?>
</section>
<?php if (($flashMessage ?? null) !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>
