<div class="launch-intro">
    <div>
        <p class="eyebrow">PLATFORM READINESS</p>
        <h2>Launch checklist</h2>
        <p>Check runtime, deployment, storage, security, and provider requirements. Expand “How to fix” on any failed item for remediation steps. Provider credentials are encrypted before database storage and never shown again.</p>
    </div>
    <span class="launch-count"><?= e((string) count(array_filter($requirements, static fn (array $requirement): bool => $requirement['ready']))) ?> / <?= e((string) count($requirements)) ?> ready</span>
</div>

<div class="requirements-list">
    <?php foreach ($requirements as $requirement): ?>
        <div class="requirement-row">
            <span class="requirement-icon <?= $requirement['ready'] ? 'ready' : '' ?>"><?= $requirement['ready'] ? '✓' : '•' ?></span>
            <div class="requirement-copy">
                <strong><?= e($requirement['label']) ?></strong>
                <span><?= e($requirement['detail']) ?></span>
                <?php if (!$requirement['ready']): ?>
                    <details class="requirement-fix">
                        <summary>How to fix</summary>
                        <p><?= e($requirement['fix']) ?></p>
                    </details>
                <?php endif; ?>
            </div>
            <span class="requirement-status"><?= $requirement['ready'] ? 'Ready' : 'Required' ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="provider-grid">
    <?php foreach ($providerDefinitions as $providerKey => $definition): ?>
        <?php $status = $providerStatuses[$providerKey]; ?>
        <article class="provider-card">
            <div class="provider-card-heading">
                <div><p class="eyebrow">API INTEGRATION</p><h3><?= e($definition['label']) ?></h3></div>
                <span class="provider-badge <?= $status['verified_at'] !== null ? 'verified' : '' ?>">
                    <?= $status['verified_at'] !== null ? 'Verified' : ($status['configured'] ? 'Test required' : 'Not configured') ?>
                </span>
            </div>
            <?php if ($providerKey === 'stripe'): ?>
                <p class="provider-saved">Configure Stripe webhooks to send <code>customer.subscription.created</code>, <code>customer.subscription.updated</code>, <code>customer.subscription.deleted</code>, <code>checkout.session.completed</code>, and <code>checkout.session.async_payment_succeeded</code> to <code><?= e(rtrim((string) \App\Support\Env::get('APP_URL', ''), '/') . '/webhooks/stripe') ?></code>.</p>
            <?php endif; ?>
            <?php if ($status['configured_fields'] !== []): ?>
                <p class="provider-saved">Stored fields: <?= e((string) count($status['configured_fields'])) ?> of <?= e((string) count($definition['fields'])) ?>. Secret values are hidden.</p>
            <?php endif; ?>
            <?php if ($status['last_test_message'] !== null): ?>
                <p class="provider-test-note <?= $status['verified_at'] !== null ? 'success' : 'error' ?>"><?= e($status['last_test_message']) ?></p>
            <?php endif; ?>
            <form method="post" action="/admin/integrations/save" class="provider-form">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="provider" value="<?= e($providerKey) ?>">
                <?php foreach ($definition['fields'] as $field => $metadata): ?>
                    <label for="<?= e($providerKey . '-' . $field) ?>"><?= e($metadata['label']) ?>
                        <input
                            id="<?= e($providerKey . '-' . $field) ?>"
                            name="<?= e($field) ?>"
                            type="<?= e($metadata['type']) ?>"
                            maxlength="4096"
                            autocomplete="<?= $metadata['type'] === 'password' ? 'new-password' : 'off' ?>"
                            placeholder="<?= isset($status['configured_fields']) && in_array($field, $status['configured_fields'], true) ? 'Leave blank to keep the stored value' : 'Enter ' . strtolower($metadata['label']) ?>"
                        >
                    </label>
                <?php endforeach; ?>
                <small class="field-hint">Blank fields keep existing credentials. Tests make a live, read-only request to <?= e($definition['label']) ?>.</small>
                <div class="provider-actions">
                    <button class="button button-secondary" type="submit" name="action" value="save">Save credentials</button>
                    <button class="button" type="submit" name="action" value="test">Save &amp; verify</button>
                </div>
            </form>
        </article>
    <?php endforeach; ?>
</div>

<div class="launch-action-panel">
    <div>
        <p class="eyebrow">IN-APP STATUS</p>
        <h3><?= $isLaunched ? 'Garwin AI is marked launch-ready' : 'Ready to mark the platform launch-ready?' ?></h3>
        <p>This does not deploy code, configure DNS, or activate incomplete product integrations.</p>
    </div>
    <form method="post" action="/admin/launch">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <button class="button" type="submit" <?= $isLaunched || count(array_filter($requirements, static fn (array $requirement): bool => $requirement['ready'])) !== count($requirements) ? 'disabled' : '' ?>>
            <?= $isLaunched ? 'Already launch-ready' : 'Launch Garwin AI' ?>
        </button>
    </form>
</div>
