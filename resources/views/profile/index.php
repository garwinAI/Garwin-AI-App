<?php
$fullName = $user['first_name'] . ' ' . $user['last_name'];
$memberSince = isset($account['created_at']) ? date('F j, Y', strtotime((string) $account['created_at'])) : '—';
$usedGb = number_format(((int) ($account['used_bytes'] ?? 0)) / 1073741824, 2);
$quotaGb = rtrim(rtrim(number_format(((int) ($account['quota_bytes'] ?? 0)) / 1073741824, 2), '0'), '.');
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">YOUR ACCOUNT</p>
        <h1>Profile</h1>
        <p class="page-subtitle">How your account looks across Garwin AI.</p>
    </div>
</section>

<section class="profile-card">
    <span class="avatar profile-avatar"><?= e(mb_strtoupper(mb_substr($user['first_name'], 0, 1))) ?></span>
    <div>
        <h2><?= e($fullName) ?></h2>
        <p><?= e($user['email']) ?></p>
    </div>
</section>

<section class="account-details">
    <div><span class="detail-label">GarwinAI ID</span><strong><?= e($user['user_id']) ?></strong></div>
    <div><span class="detail-label">Membership</span><strong><?= e((string) ($account['plan_name'] ?? 'Free')) ?></strong></div>
    <div><span class="detail-label">Member since</span><strong><?= e($memberSince) ?></strong></div>
    <div><span class="detail-label">Storage</span><strong><?= e($usedGb) ?> GB of <?= e($quotaGb) ?> GB</strong></div>
    <div><span class="detail-label">Manage</span><strong><a href="/settings">Settings</a> · <a href="/billing">Billing</a></strong></div>
</section>
