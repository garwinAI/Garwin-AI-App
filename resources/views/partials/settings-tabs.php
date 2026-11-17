<?php $settingsTab ??= 'preferences'; ?>
<nav class="settings-tabs" aria-label="Settings sections">
    <a href="/settings" class="<?= $settingsTab === 'preferences' ? 'selected' : '' ?>" <?= $settingsTab === 'preferences' ? 'aria-current="page"' : '' ?>>Preferences</a>
    <a href="/billing" class="<?= $settingsTab === 'billing' ? 'selected' : '' ?>" <?= $settingsTab === 'billing' ? 'aria-current="page"' : '' ?>>Billing &amp; membership</a>
</nav>
