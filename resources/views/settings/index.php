<section class="page-heading">
    <div>
        <p class="eyebrow">YOUR WORKSPACE</p>
        <h1>Settings</h1>
        <p class="page-subtitle">Choose how your workspace looks and behaves.</p>
    </div>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>

<section class="settings-card">
    <form class="settings-form" method="post" action="/settings">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <label>
            Weather location
            <input type="text" name="weather_location" maxlength="100" value="<?= e((string) ($preferences['weather_location'] ?? '')) ?>" placeholder="City, country">
            <span class="field-hint">Leave blank to show weather for your current location automatically. The city or your approximate location is sent to Open-Meteo to look up weather.</span>
        </label>
        <label>
            Clock format
            <select name="time_format">
                <option value="24" <?= ($preferences['time_format'] ?? '12') === '24' ? 'selected' : '' ?>>24-hour</option>
                <option value="12" <?= ($preferences['time_format'] ?? '12') === '12' ? 'selected' : '' ?>>12-hour</option>
            </select>
        </label>
        <label>
            Storage display
            <select name="storage_view">
                <option value="grid" <?= ($preferences['storage_view'] ?? 'grid') === 'grid' ? 'selected' : '' ?>>Grid</option>
                <option value="list" <?= ($preferences['storage_view'] ?? 'grid') === 'list' ? 'selected' : '' ?>>List</option>
            </select>
        </label>
        <button class="button" type="submit">Save settings</button>
    </form>
</section>
