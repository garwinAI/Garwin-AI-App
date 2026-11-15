<article class="appzone-item">
    <div class="appzone-item-top">
        <span class="appzone-item-icon" aria-hidden="true">▦</span>
        <span class="appzone-price"><?= (int) $item['price_cents'] === 0 ? 'Free' : '$' . number_format((int) $item['price_cents'] / 100, 2) ?></span>
    </div>
    <h3><?= e($item['name']) ?></h3>
    <p class="appzone-description"><?= e($item['description']) ?></p>
    <div class="appzone-rating-summary" aria-label="<?= number_format((float) $item['avg_rating'], 1) ?> out of 5 stars from <?= e((string) $item['rating_count']) ?> ratings">
        <span class="stars-display" aria-hidden="true">
            <?php for ($star = 1; $star <= 5; $star++): ?><span class="<?= $star <= (int) round((float) $item['avg_rating']) ? 'filled' : '' ?>">★</span><?php endfor; ?>
        </span>
        <span><?= number_format((float) $item['avg_rating'], 1) ?> (<?= e((string) $item['rating_count']) ?>)</span>
    </div>
    <p class="appzone-item-meta"><?= e((string) $item['view_count']) ?> views · <?= e((string) $item['install_count']) ?> installs</p>
    <form class="star-rating-form" method="post" action="/appzone/rate">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="app_id" value="<?= e((string) $item['id']) ?>">
        <fieldset>
            <legend><?= (int) $item['user_rating'] > 0 ? 'Your rating' : 'Rate this app' ?></legend>
            <div class="star-rating-options">
                <?php for ($star = 1; $star <= 5; $star++): ?>
                    <?php $ratingId = $sectionKey . '-app-' . (int) $item['id'] . '-rating-' . $star; ?>
                    <input type="radio" id="<?= e($ratingId) ?>" name="rating" value="<?= $star ?>" <?= (int) $item['user_rating'] === $star ? 'checked' : '' ?> required>
                    <label class="<?= $star <= (int) $item['user_rating'] ? 'is-rated' : '' ?>" for="<?= e($ratingId) ?>" aria-label="<?= $star ?> star<?= $star === 1 ? '' : 's' ?>">★</label>
                <?php endfor; ?>
                <button class="button button-small" type="submit">Save</button>
            </div>
        </fieldset>
    </form>
    <div class="appzone-item-actions">
        <?php if ((int) $item['installed'] > 0): ?>
            <span class="installed-label">Added to workspace</span>
        <?php elseif ((int) $item['price_cents'] === 0): ?>
            <form method="post" action="/appzone/install">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="app_id" value="<?= e((string) $item['id']) ?>">
                <button class="button button-small" type="submit">Install</button>
            </form>
        <?php else: ?>
            <form method="post" action="/appzone/purchase">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="app_id" value="<?= e((string) $item['id']) ?>">
                <button class="button button-small" type="submit">Purchase</button>
            </form>
        <?php endif; ?>
        <?php if ((int) $item['installed'] > 0 || (int) $item['price_cents'] === 0): ?>
            <a href="/appzone/open?id=<?= e((string) $item['id']) ?>" target="_blank" rel="noopener noreferrer">Visit app ↗</a>
        <?php else: ?>
            <span class="installed-label">Purchase required</span>
        <?php endif; ?>
    </div>
</article>
