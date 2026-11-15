<section class="page-heading">
    <div>
        <p class="eyebrow">APPS</p>
        <h1>AppZone</h1>
        <p class="page-subtitle">Discover apps for your Garwin AI workspace.</p>
    </div>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>
<?php if ($checkoutState === 'success'): ?>
    <div class="admin-alert" role="status">Payment submitted. Your app will appear in your workspace after Stripe confirms the payment.</div>
<?php elseif ($checkoutState === 'cancel'): ?>
    <div class="admin-alert" role="status">Checkout was canceled. No purchase was made.</div>
<?php endif; ?>

<?php
$hasPurchases = count(array_filter($mostPurchased, static fn (array $item): bool => (int) $item['purchase_count'] > 0)) > 0;
$topItems = array_values(array_filter($topItems, static fn (array $item): bool => (int) $item['rating_count'] > 0));
?>
<section class="appzone-featured">
    <?php foreach ([
        ['title' => 'Top Items', 'items' => $topItems, 'key' => 'top'],
        ['title' => 'Most Viewed', 'items' => $mostViewed, 'key' => 'viewed'],
        ['title' => 'Most purchased', 'items' => $mostPurchased, 'key' => 'purchased'],
    ] as $section): ?>
        <div class="appzone-section-heading">
            <div><p class="eyebrow">APPZONE</p><h2><?= e($section['title']) ?></h2></div>
            <a class="text-link" href="/appzone?sort=<?= e($section['key']) ?>#all-items">View all →</a>
        </div>
        <?php if ($section['items'] === [] || ($section['key'] === 'purchased' && !$hasPurchases)): ?>
            <p class="appzone-empty-note"><?php
                if ($section['key'] === 'purchased') {
                    echo 'Purchase rankings will appear as users buy apps.';
                } elseif ($section['key'] === 'top' && $apps !== []) {
                    echo 'Top rankings will appear after users rate published apps.';
                } else {
                    echo 'No published apps yet. Check back soon.';
                }
            ?></p>
        <?php else: ?>
            <div class="appzone-grid">
                <?php foreach ($section['items'] as $item): ?>
                    <?php $sectionKey = $section['key']; require __DIR__ . '/item-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</section>

<section class="appzone-all" id="all-items">
    <div class="appzone-section-heading">
        <div><p class="eyebrow">DISCOVER</p><h2>All Items</h2></div>
        <form class="appzone-sort" method="get" action="/appzone">
            <label for="appzone-sort">Sort by</label>
            <select id="appzone-sort" name="sort">
                <?php foreach (['top' => 'Top rated', 'viewed' => 'Most viewed', 'purchased' => 'Most purchased', 'price-low' => 'Price: low to high', 'price-high' => 'Price: high to low', 'name' => 'Name'] as $sortKey => $sortLabel): ?>
                    <option value="<?= e($sortKey) ?>" <?= $sort === $sortKey ? 'selected' : '' ?>><?= e($sortLabel) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button button-small" type="submit">Apply</button>
        </form>
    </div>
    <?php if ($apps === []): ?>
        <section class="appzone-empty">
            <span class="appzone-empty-icon" aria-hidden="true">▦</span>
            <h2>AppZone is getting ready</h2>
            <p>New apps will appear here when an admin publishes them.</p>
        </section>
    <?php else: ?>
        <div class="appzone-grid">
            <?php foreach ($apps as $item): ?>
                <?php $sectionKey = 'all'; require __DIR__ . '/item-card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
