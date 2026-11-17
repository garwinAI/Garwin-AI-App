<section class="app-locked">
    <?php $iconKey = $appKey; $iconSize = 'is-large'; require dirname(__DIR__) . '/partials/app-icon.php'; ?>
    <p class="eyebrow">PAID PLAN APP</p>
    <h1><?= e($app['name']) ?></h1>
    <p><?= e($app['description']) ?> <?= e($app['name']) ?> comes with paid memberships. Media Viewer and Document Viewer are included on every plan.</p>
    <div class="app-locked-actions">
        <a class="button" href="/billing">See plans</a>
        <a class="button button-secondary" href="/dashboard">Back to dashboard</a>
    </div>
</section>
