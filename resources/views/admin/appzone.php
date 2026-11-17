<section class="page-heading">
    <div>
        <p class="eyebrow">ADMINISTRATION</p>
        <h1>AppZone catalog</h1>
        <p class="page-subtitle">Create and publish AppZone listings. Paid items use the configured Stripe Checkout integration.</p>
    </div>
    <span class="plan-pill"><span class="status-dot"></span> Super Admin</span>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>

<section class="admin-panel appzone-admin-form">
    <div>
        <p class="eyebrow">NEW LISTING</p>
        <h2>Add an AppZone item</h2>
        <p>Free items can be installed by users. The destination URL should provide the app or its documentation.</p>
    </div>
    <form class="settings-form" method="post" action="/admin/appzone/create">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <label>App name <input type="text" name="name" maxlength="120" required></label>
        <label>Description <textarea name="description" rows="3" maxlength="2000"></textarea></label>
        <label>Destination URL <input type="text" name="launch_url" maxlength="2048" placeholder="https://example.com/app or /dashboard" required></label>
        <label>Price (USD) <input type="number" name="price" min="0" max="99999.99" step="0.01" value="0" required></label>
        <label class="checkbox-label"><input type="checkbox" name="is_published" value="1"> Publish immediately</label>
        <button class="button" type="submit">Add listing</button>
    </form>
</section>

<section class="admin-panel appzone-admin-list">
    <div>
        <p class="eyebrow">CATALOG</p>
        <h2>AppZone items</h2>
    </div>
    <?php if ($items === []): ?>
        <p class="appzone-empty-note">No AppZone items have been created.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>App</th><th>Price</th><th>Ratings</th><th>Views</th><th>Installs</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= e($item['name']) ?></strong><br><span class="field-hint"><?= e($item['description']) ?></span></td>
                            <td><?= (int) $item['price_cents'] === 0 ? 'Free' : '$' . number_format((int) $item['price_cents'] / 100, 2) ?></td>
                            <td><?= e((string) $item['rating_count']) ?></td>
                            <td><?= e((string) $item['view_count']) ?></td>
                            <td><?= e((string) $item['install_count']) ?></td>
                            <td><?= (int) $item['is_published'] === 1 ? 'Published' : 'Draft' ?></td>
                            <td>
                                <form method="post" action="/admin/appzone/publish">
                                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="app_id" value="<?= e((string) $item['id']) ?>">
                                    <button class="button button-small button-secondary" type="submit"><?= (int) $item['is_published'] === 1 ? 'Unpublish' : 'Publish' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
