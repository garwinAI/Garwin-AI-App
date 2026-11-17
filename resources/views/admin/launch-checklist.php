<?php if ($showLaunchChecklist ?? false): ?>
    <div class="launch-overlay">
        <section class="launch-dialog" role="dialog" aria-modal="true" aria-labelledby="launch-dialog-title">
            <header class="launch-dialog-header">
                <div>
                    <p class="eyebrow">SUPER ADMIN SETUP</p>
                    <h2 id="launch-dialog-title">Before Garwin AI is ready</h2>
                    <p>Verify platform requirements and configure the required APIs. Launch-ready status does not deploy the site.</p>
                </div>
                <form method="post" action="/admin/launch/dismiss">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button class="icon-button" type="submit" aria-label="Dismiss checklist">×</button>
                </form>
            </header>
            <?php require __DIR__ . '/launch-checklist-body.php'; ?>
        </section>
    </div>
<?php else: ?>
    <section class="launch-section">
        <?php require __DIR__ . '/launch-checklist-body.php'; ?>
    </section>
<?php endif; ?>
