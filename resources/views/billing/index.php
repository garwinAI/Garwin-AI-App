<section class="page-heading">
    <div>
        <p class="eyebrow">YOUR WORKSPACE</p>
        <h1>Settings</h1>
        <p class="page-subtitle">Review your plan, limits, and subscription status.</p>
    </div>
    <span class="plan-pill"><span class="status-dot"></span><?= e(ucfirst($membership['plan_key'])) ?> plan</span>
</section>

<?php $settingsTab = 'billing'; require dirname(__DIR__) . '/partials/settings-tabs.php'; ?>

<?php if ($flashMessage !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>

<?php if ($checkoutState === 'success'): ?>
    <div class="admin-alert success" role="status">Checkout completed or is processing. Your plan updates after Stripe confirms the subscription.</div>
<?php elseif ($checkoutState === 'cancel'): ?>
    <div class="admin-alert" role="status">Checkout was canceled. Your membership has not changed.</div>
<?php endif; ?>

<section class="admin-panel">
    <div>
        <p class="eyebrow">CURRENT MEMBERSHIP</p>
        <h2><?= e(ucfirst($membership['plan_key'])) ?> plan</h2>
        <p>Status: <?= e(ucfirst(str_replace('_', ' ', $membership['status']))) ?>
            <?php if ($membership['current_period_end'] !== null): ?>
                · Current period ends <?= e(gmdate('M j, Y', strtotime($membership['current_period_end']))) ?>
            <?php endif; ?>
            <?php if ($membership['cancel_at_period_end']): ?>
                · Scheduled to cancel at period end
            <?php endif; ?>
        </p>
    </div>
    <?php if ($membership['stripe_customer_id'] !== null): ?>
        <form method="post" action="/billing/portal">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <button class="button button-secondary" type="submit">Manage subscription</button>
        </form>
    <?php endif; ?>
</section>

<section class="admin-summary-grid billing-plans">
    <?php foreach ($plans as $plan): ?>
        <article class="admin-summary-card billing-plan-card">
            <span class="detail-label"><?= e($plan['display_name']) ?> membership</span>
            <strong><?= $plan['price_cents'] === 0 ? 'Free' : '$' . number_format($plan['price_cents'] / 100, 2) . ' / month' ?></strong>
            <span><?= e(number_format($plan['storage_quota_bytes'] / 1073741824, 0)) ?> GB storage · $<?= e(number_format($plan['ai_budget_cents'] / 100, 2)) ?> monthly AI budget · <?= e((string) $plan['team_seats']) ?> included team seats</span>
            <?php if ($plan['plan_key'] === 'pro' && $membership['plan_key'] !== 'pro' && $plan['enabled']): ?>
                <form method="post" action="/billing/checkout">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button class="button" type="submit">Upgrade to Pro</button>
                </form>
            <?php elseif ($membership['plan_key'] === $plan['plan_key']): ?>
                <span class="provider-badge verified">Current plan</span>
            <?php elseif (!$plan['enabled']): ?>
                <span class="provider-badge">Unavailable</span>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>

<p class="field-hint">Payments are handled by Stripe. Card details are never stored by GarwinAI. Membership access is updated only after a verified Stripe webhook.</p>
