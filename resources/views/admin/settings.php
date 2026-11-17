<section class="page-heading">
    <div>
        <p class="eyebrow">SUPER ADMIN</p>
        <h1>Admin settings</h1>
        <p class="page-subtitle">Configure launch integrations and isolated demo accounts.</p>
    </div>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>

<nav class="settings-tabs" aria-label="Admin settings">
    <a href="/admin/settings?tab=launch" class="<?= $tab === 'launch' ? 'selected' : '' ?>" <?= $tab === 'launch' ? 'aria-current="page"' : '' ?>>Launch checklist</a>
    <a href="/admin/settings?tab=plans" class="<?= $tab === 'plans' ? 'selected' : '' ?>" <?= $tab === 'plans' ? 'aria-current="page"' : '' ?>>Membership plans</a>
    <a href="/admin/settings?tab=demo-users" class="<?= $tab === 'demo-users' ? 'selected' : '' ?>" <?= $tab === 'demo-users' ? 'aria-current="page"' : '' ?>>Demo Users <span class="tab-count"><?= e((string) $demoUserCount) ?></span></a>
</nav>

<?php if ($tab === 'plans'): ?>
    <section class="admin-panel">
        <div>
            <p class="eyebrow">MEMBERSHIP CATALOG</p>
            <h2>Plans, pricing &amp; limits</h2>
            <p>Changes apply to new checkouts immediately and update storage and AI budgets for current members of the edited plan.</p>
        </div>
    </section>
    <section class="provider-grid">
        <?php foreach ($membershipPlans as $plan): ?>
            <article class="provider-card">
                <div class="provider-card-heading">
                    <div><p class="eyebrow">MEMBERSHIP PLAN</p><h3><?= e($plan['display_name']) ?></h3></div>
                    <span class="provider-badge <?= $plan['enabled'] ? 'verified' : '' ?>"><?= $plan['enabled'] ? 'Enabled' : 'Disabled' ?></span>
                </div>
                <form method="post" action="/admin/membership-plans" class="provider-form">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="plan_key" value="<?= e($plan['plan_key']) ?>">
                    <label for="plan-<?= e($plan['plan_key']) ?>-price">Monthly price (USD)
                        <input id="plan-<?= e($plan['plan_key']) ?>-price" type="number" name="monthly_price" min="0" max="999999.99" step="0.01" value="<?= e(number_format($plan['price_cents'] / 100, 2, '.', '')) ?>" <?= $plan['plan_key'] === 'free' ? 'readonly' : 'required' ?>>
                    </label>
                    <label for="plan-<?= e($plan['plan_key']) ?>-storage">Storage quota (GB)
                        <input id="plan-<?= e($plan['plan_key']) ?>-storage" type="number" name="storage_gb" min="1" max="100000" value="<?= e((string) (int) round($plan['storage_quota_bytes'] / 1073741824)) ?>" required>
                    </label>
                    <label for="plan-<?= e($plan['plan_key']) ?>-ai">Monthly AI budget (USD)
                        <input id="plan-<?= e($plan['plan_key']) ?>-ai" type="number" name="ai_budget" min="0" max="100000" step="0.01" value="<?= e(number_format($plan['ai_budget_cents'] / 100, 2, '.', '')) ?>" required>
                    </label>
                    <label for="plan-<?= e($plan['plan_key']) ?>-seats">Included team seats
                        <input id="plan-<?= e($plan['plan_key']) ?>-seats" type="number" name="team_seats" min="0" max="10000" value="<?= e((string) $plan['team_seats']) ?>" required>
                    </label>
                    <?php if ($plan['plan_key'] !== 'free'): ?>
                        <label class="confirm-label"><input type="checkbox" name="enabled" value="1" <?= $plan['enabled'] ? 'checked' : '' ?>> Plan available for new subscriptions</label>
                    <?php else: ?>
                        <p class="field-hint">The Free plan is always available and has no recurring price.</p>
                    <?php endif; ?>
                    <button class="button" type="submit">Save <?= e($plan['display_name']) ?> plan</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
<?php elseif ($tab === 'demo-users'): ?>
    <section class="admin-panel demo-user-panel">
        <div>
            <p class="eyebrow">TEST ACCOUNTS</p>
            <h2>Create demo users</h2>
            <p>Demo accounts use reserved <code>.invalid</code> email addresses and random passwords, cannot be mistaken for real users, and are tagged separately from customer accounts.</p>
        </div>
        <form class="demo-create-form" method="post" action="/admin/demo-users/create">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <label for="demo-amount">Number to add</label>
            <div class="demo-create-controls">
                <input id="demo-amount" type="number" name="amount" min="1" max="100" value="5" required>
                <button class="button" type="submit">Add demo users</button>
            </div>
            <small class="field-hint">Add 1–100 accounts per request.</small>
        </form>
    </section>

    <section class="admin-panel demo-list-panel">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">ISOLATED ACCOUNTS</p>
                <h2>Demo Users <span class="tab-count"><?= e((string) $demoUserCount) ?></span></h2>
            </div>
            <?php if ($demoUserCount > 0): ?>
                <form method="post" action="/admin/demo-users/remove" data-confirm="Permanently remove every demo user and its private files?">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <label class="confirm-label"><input type="checkbox" name="confirm_remove" value="yes" required> Confirm removal</label>
                    <button class="button button-danger" type="submit">Remove all demo users</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if ($demoUsers === []): ?>
            <div class="empty-state">
                <span class="empty-icon">◇</span>
                <strong>No demo users yet</strong>
                <span>Choose an amount above to add isolated test accounts.</span>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Name</th><th>Email</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($demoUsers as $demoUser): ?>
                        <tr>
                            <td><?= e($demoUser['first_name'] . ' ' . $demoUser['last_name']) ?></td>
                            <td><code><?= e($demoUser['email']) ?></code></td>
                            <td><?= e($demoUser['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($demoUserCount > count($demoUsers)): ?>
                <p class="field-hint">Showing the newest <?= e((string) count($demoUsers)) ?> accounts. Remove all deletes every demo account.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php else: ?>
    <?php require __DIR__ . '/launch-checklist.php'; ?>
<?php endif; ?>
