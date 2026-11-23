<?php
require __DIR__ . '/../partials/workspace-toolbar.php';
$searchIcons = require __DIR__ . '/../partials/icons.php';
$searchIcon = static fn (string $name): string => '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">' . ($searchIcons[$name] ?? $searchIcons['app']) . '</svg>';
$total = array_sum(array_map('count', $results));
?>
<section class="page-heading">
    <div>
        <p class="eyebrow">SEARCH</p>
        <h1><?= $query === '' ? 'Search your workspace' : 'Results for “' . e($query) . '”' ?></h1>
        <p class="page-subtitle"><?= $query === '' ? 'Find apps, pages, docs, folders, events and messages.' : ($total === 0 ? 'Nothing matched. Try a different word.' : $total . ' ' . ($total === 1 ? 'match' : 'matches')) ?></p>
    </div>
</section>

<?php foreach ($results as $group => $items): ?>
    <section class="search-group">
        <h2><?= e($group) ?></h2>
        <ul>
            <?php foreach ($items as $result): ?>
                <li><a href="<?= e($result['href']) ?>"><?= icon_tile($result['icon']) ?><span><strong><?= e($result['title']) ?></strong><small><?= e($result['detail']) ?></small></span></a></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endforeach; ?>
