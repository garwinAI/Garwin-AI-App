<section class="page-heading">
    <div>
        <p class="eyebrow">GARWINAI ADMINISTRATION</p>
        <h1>Platform overview</h1>
        <p class="page-subtitle">Manage launch readiness and demo accounts.</p>
    </div>
    <span class="plan-pill"><span class="status-dot"></span> Super Admin</span>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>

<section class="admin-summary-grid">
    <article class="admin-summary-card">
        <span class="detail-label">Platform status</span>
        <strong><?= $isLaunched ? 'Launch-ready' : 'Setup in progress' ?></strong>
        <span><?= $isLaunched ? 'In-app readiness marked' : 'API and deployment settings required' ?></span>
    </article>
    <article class="admin-summary-card">
        <span class="detail-label">Demo users</span>
        <strong><?= e((string) $demoUserCount) ?></strong>
        <a href="/admin/settings?tab=demo-users">Manage demo accounts</a>
    </article>
    <article class="admin-summary-card">
        <span class="detail-label">Launch readiness checks</span>
        <strong><?= e((string) count(array_filter($requirements, static fn (array $requirement): bool => $requirement['ready']))) ?>/<?= e((string) count($requirements)) ?></strong>
        <a href="/admin/settings?tab=launch">Open launch checklist</a>
    </article>
</section>

<section class="admin-panel">
    <div>
        <p class="eyebrow">LAUNCH CONTROL</p>
        <h2><?= $isLaunched ? 'Launch-ready state is active' : 'Complete the launch checklist' ?></h2>
        <p>Provider credentials are tested against their live APIs. Launch-ready only records configuration readiness in Garwin AI; it does not deploy the site or enable unfinished billing, email, or AI features.</p>
    </div>
    <a class="button" href="/admin/settings?tab=launch"><?= $isLaunched ? 'Review launch settings' : 'Configure launch checklist' ?> <span aria-hidden="true">→</span></a>
</section>

<?php if ($showLaunchChecklist): ?>
    <?php require dirname(__DIR__) . '/admin/launch-checklist.php'; ?>
<?php endif; ?>
