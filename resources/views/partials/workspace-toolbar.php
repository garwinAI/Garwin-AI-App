<?php
/**
 * The workspace toolbar: a long search bar, then Apps, Widgets (dashboard only) and the Pages menu.
 * Expects $user, $csrfToken, $icon (from the layout or the caller) and optional $toolbarWidgets, $toolbarPage.
 */
$toolbarNavigation = new App\Services\NavigationService(app_db());
$toolbarApps = $toolbarNavigation->myApps((int) $user['id']);
$toolbarPages = (new App\Services\WorkspacePageService(app_db()))->listForUser((int) $user['id']);
$toolbarIconPaths = require __DIR__ . '/icons.php';
$toolbarIcon = static fn (string $name, string $class = 'nav-icon'): string => '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true">' . ($toolbarIconPaths[$name] ?? $toolbarIconPaths['app']) . '</svg>';
$toolbarReturn = (string) ($_SERVER['REQUEST_URI'] ?? '/dashboard');
$toolbarLinks = $toolbarNavigation->menuLinks((int) $user['id']);
$linkForm = static function (?array $link, string $csrfToken, string $return): string {
    $uid = $link['id'] ?? 'new';
    $html = '<form class="menu-link-form" method="post" action="/menu-links/save">'
        . '<input type="hidden" name="_token" value="' . e($csrfToken) . '">'
        . '<input type="hidden" name="return" value="' . e($return) . '">'
        . ($link !== null ? '<input type="hidden" name="link_id" value="' . e($link['id']) . '">' : '')
        . '<div class="menu-link-fields">'
        . '<input type="text" name="label" maxlength="40" required placeholder="Name" aria-label="Link name" value="' . e($link['label'] ?? '') . '">'
        . '<input type="text" name="url" maxlength="500" required placeholder="Address, e.g. https://… or /calendar" aria-label="Link address" value="' . e($link['url'] ?? '') . '">'
        . '</div><p class="menu-link-label">Icon</p><div class="menu-link-icons">';
    foreach (App\Services\NavigationService::MENU_LINK_ICONS as $index => $iconName) {
        $checked = ($link['icon'] ?? 'link') === $iconName;
        $html .= '<label title="' . e($iconName) . '"><input type="radio" name="icon" value="' . e($iconName) . '"' . ($checked ? ' checked' : '') . '>' . icon_tile($iconName, 'sm', 'slate') . '</label>';
    }
    $html .= '</div><p class="menu-link-label">Color</p><div class="menu-link-colors">';
    foreach (ICON_TILE_PALETTE as $color) {
        $checked = ($link['color'] ?? 'sky') === $color;
        $html .= '<label title="' . e($color) . '"><input type="radio" name="color" value="' . e($color) . '"' . ($checked ? ' checked' : '') . '><span class="color-swatch tile-' . e($color) . '"></span></label>';
    }

    return $html . '</div><button class="button button-small" type="submit">' . ($link === null ? 'Add to menu' : 'Save link') . '</button></form>';
};
$toolbarWidgets ??= null;
$toolbarPage ??= null;
?>
<div class="workspace-toolbar">
    <form class="workspace-search" method="get" action="/search" role="search">
        <?= $toolbarIcon('search', 'workspace-search-icon') ?>
        <input type="search" name="q" value="<?= e((string) ($query ?? '')) ?>" placeholder="Search apps, pages, docs, folders, events and messages" aria-label="Search your workspace" maxlength="100">
    </form>

    <details class="toolbar-popover" data-toolbar-popover>
        <summary class="toolbar-button icon-tile-button" title="Apps" aria-label="Apps"><?= icon_tile('apps') ?></summary>
        <div class="toolbar-panel toolbar-panel-apps" role="dialog" aria-label="Your apps">
            <div class="toolbar-panel-head"><strong>Your apps</strong><a href="/appzone">Get more in AppZone →</a></div>
            <ul class="apps-list">
                <?php foreach ($toolbarApps as $app): ?>
                    <li class="<?= $app['active'] ? '' : 'is-off' ?>">
                        <?= icon_tile($app['icon'], 'lg', $app['active'] ? null : 'gray') ?>
                        <span class="apps-list-text"><strong><?= e($app['label']) ?></strong><small><?= $app['active'] ? e($app['description']) : 'Off' ?></small></span>
                        <form method="post" action="/apps/toggle">
                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="key" value="<?= e($app['key']) ?>">
                            <input type="hidden" name="return" value="<?= e($toolbarReturn) ?>">
                            <button class="app-switch <?= $app['active'] ? 'on' : '' ?>" type="submit" role="switch" aria-checked="<?= $app['active'] ? 'true' : 'false' ?>" title="<?= $app['active'] ? 'Deactivate ' : 'Activate ' ?><?= e($app['label']) ?>" aria-label="<?= $app['active'] ? 'Deactivate ' : 'Activate ' ?><?= e($app['label']) ?>"><span></span></button>
                        </form>
                        <a class="apps-list-settings" href="/apps/settings?app=<?= e(rawurlencode($app['key'])) ?>" title="<?= e($app['label']) ?> settings" aria-label="<?= e($app['label']) ?> settings"><?= icon_tile('gear', 'sm') ?></a>
                        <?php if ($app['active']): ?>
                            <a class="apps-go" href="<?= e($app['href']) ?>">GO</a>
                        <?php else: ?>
                            <span class="apps-go disabled" title="Activate <?= e($app['label']) ?> to open it">GO</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </details>

    <?php if ($toolbarWidgets !== null): ?>
        <details class="toolbar-popover" data-toolbar-popover data-widget-gallery>
            <summary class="toolbar-button icon-tile-button" title="Widgets" aria-label="Widgets"><?= icon_tile('widgets') ?></summary>
            <div class="toolbar-panel toolbar-panel-widgets" role="dialog" aria-label="Widgets">
                <div class="toolbar-panel-head"><strong>Widgets</strong><span>Drag onto your page, or click Add</span></div>
                <ul class="widget-gallery">
                    <?php foreach ($toolbarWidgets as $widgetId => [$widgetTitle, $widgetIcon, $widgetDescription]): ?>
                        <li class="widget-gallery-item" draggable="true" data-gallery-widget="<?= e($widgetId) ?>">
                            <?= icon_tile($widgetIcon, 'lg') ?>
                            <span class="apps-list-text"><strong><?= e($widgetTitle) ?></strong><small><?= e($widgetDescription) ?></small></span>
                            <button class="widget-gallery-add" type="button" data-gallery-add="<?= e($widgetId) ?>">Add</button>
                            <span class="widget-gallery-on">On page</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </details>
    <?php endif; ?>

    <details class="toolbar-popover" data-toolbar-popover>
        <summary class="toolbar-button icon-tile-button" title="Pages, view and menu" aria-label="Pages, view and menu"><?= icon_tile('more') ?></summary>
        <div class="toolbar-panel toolbar-panel-pages" role="dialog" aria-label="Pages">
            <div class="toolbar-section-title first"><?= icon_tile('pages', 'xs') ?>Pages</div>
            <p class="toolbar-panel-label">Add a page</p>
            <div class="pages-add">
                <?php foreach (App\Services\WorkspacePageService::TYPES as $typeKey => $type): ?>
                    <form method="post" action="/pages/create">
                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="type" value="<?= e($typeKey) ?>">
                        <button type="submit"><?= icon_tile($type['icon']) ?><?= e($type['label']) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
            <?php if ($toolbarPage !== null): ?>
                <p class="toolbar-panel-label">This page</p>
                <div class="pages-actions">
                    <form method="post" action="/pages/duplicate">
                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="page_id" value="<?= (int) $toolbarPage['id'] ?>">
                        <button type="submit"><?= icon_tile('copy', 'sm') ?>Duplicate</button>
                    </form>
                    <form method="post" action="/pages/delete" data-confirm="Delete “<?= e($toolbarPage['title']) ?>”? This can't be undone.">
                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="page_id" value="<?= (int) $toolbarPage['id'] ?>">
                        <button class="danger" type="submit"><?= icon_tile('trash', 'sm') ?>Delete</button>
                    </form>
                </div>
            <?php endif; ?>
            <p class="toolbar-panel-label">Your pages</p>
            <?php if ($toolbarPages === []): ?>
                <p class="toolbar-empty">No pages yet. Add one above.</p>
            <?php else: ?>
                <ul class="pages-list">
                    <?php foreach ($toolbarPages as $listedPage): $listedType = App\Services\WorkspacePageService::TYPES[$listedPage['page_type']] ?? App\Services\WorkspacePageService::TYPES['doc']; ?>
                        <li>
                            <a href="/pages/view?id=<?= (int) $listedPage['id'] ?>"><?= icon_tile($listedType['icon'], 'sm') ?><span><?= e($listedPage['title']) ?></span></a>
                            <form method="post" action="/pages/duplicate">
                                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="page_id" value="<?= (int) $listedPage['id'] ?>">
                                <button class="icon-button" type="submit" title="Duplicate <?= e($listedPage['title']) ?>" aria-label="Duplicate <?= e($listedPage['title']) ?>"><?= icon_tile('copy', 'sm') ?></button>
                            </form>
                            <form method="post" action="/pages/delete" data-confirm="Delete “<?= e($listedPage['title']) ?>”? This can't be undone.">
                                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="page_id" value="<?= (int) $listedPage['id'] ?>">
                                <input type="hidden" name="return" value="<?= e($toolbarReturn) ?>">
                                <button class="icon-button" type="submit" title="Delete <?= e($listedPage['title']) ?>" aria-label="Delete <?= e($listedPage['title']) ?>"><?= icon_tile('trash', 'sm') ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="toolbar-section-title"><?= icon_tile('window', 'xs') ?>View</div>
            <p class="toolbar-panel-label">Add a window to your dashboard</p>
            <div class="view-add">
                <form method="post" action="/dashboard/windows">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="kind" value="note">
                    <button type="submit"><?= icon_tile('note') ?><span><strong>Note</strong><small>A window you can write in</small></span></button>
                </form>
                <form class="view-add-page" method="post" action="/dashboard/windows">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="kind" value="page">
                    <?= icon_tile('pages') ?>
                    <select name="page_id" aria-label="Page to show in a window" <?= $toolbarPages === [] ? 'disabled' : '' ?>>
                        <?php if ($toolbarPages === []): ?><option>Add a page first</option><?php endif; ?>
                        <?php foreach ($toolbarPages as $listedPage): ?><option value="<?= (int) $listedPage['id'] ?>"><?= e($listedPage['title']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="button button-small" type="submit" <?= $toolbarPages === [] ? 'disabled' : '' ?>>Add</button>
                </form>
            </div>

            <div class="toolbar-section-title"><?= icon_tile('link', 'xs') ?>Menu</div>
            <p class="toolbar-panel-label">Add a link to your sidebar menu</p>
            <?= $linkForm(null, $csrfToken, $toolbarReturn) ?>
            <?php if ($toolbarLinks !== []): ?>
                <p class="toolbar-panel-label">Your menu links</p>
                <ul class="menu-links-list">
                    <?php foreach ($toolbarLinks as $linkIndex => $menuLink): ?>
                        <li>
                            <details class="menu-link-edit">
                                <summary>
                                    <?= icon_tile($menuLink['icon'], 'sm', $menuLink['color']) ?>
                                    <span class="menu-link-name"><?= e($menuLink['label']) ?></span>
                                    <span class="menu-link-edit-hint">Edit</span>
                                </summary>
                                <?= $linkForm($menuLink, $csrfToken, $toolbarReturn) ?>
                            </details>
                            <?php foreach (['up' => 'up', 'down' => 'down'] as $direction => $arrow): $disabled = ($direction === 'up' && $linkIndex === 0) || ($direction === 'down' && $linkIndex === count($toolbarLinks) - 1); ?>
                                <form method="post" action="/menu-links/move">
                                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="link_id" value="<?= e($menuLink['id']) ?>">
                                    <input type="hidden" name="direction" value="<?= $direction ?>">
                                    <input type="hidden" name="return" value="<?= e($toolbarReturn) ?>">
                                    <button class="icon-button" type="submit" <?= $disabled ? 'disabled' : '' ?> title="Move <?= $direction ?>" aria-label="Move <?= e($menuLink['label']) ?> <?= $direction ?>"><?= $direction === 'up' ? '↑' : '↓' ?></button>
                                </form>
                            <?php endforeach; ?>
                            <form method="post" action="/menu-links/delete" data-confirm="Remove “<?= e($menuLink['label']) ?>” from your menu?">
                                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                <input type="hidden" name="link_id" value="<?= e($menuLink['id']) ?>">
                                <input type="hidden" name="return" value="<?= e($toolbarReturn) ?>">
                                <button class="icon-button" type="submit" title="Remove <?= e($menuLink['label']) ?>" aria-label="Remove <?= e($menuLink['label']) ?>"><?= icon_tile('trash', 'sm') ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </details>
</div>
