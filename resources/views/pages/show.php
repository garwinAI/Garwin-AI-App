<?php
$toolbarPage = $page;
require __DIR__ . '/../partials/workspace-toolbar.php';
$columns = App\Services\WorkspacePageService::SHEET_COLUMNS;
$rows = App\Services\WorkspacePageService::SHEET_ROWS;
?>
<section class="page-editor" data-page-editor data-page-id="<?= (int) $page['id'] ?>" data-page-type="<?= e($page['page_type']) ?>" data-csrf="<?= e($csrfToken) ?>">
    <header class="page-editor-head">
        <p class="eyebrow"><?= e(mb_strtoupper($pageType['label'])) ?></p>
        <input class="page-title-input" type="text" value="<?= e($page['title']) ?>" maxlength="<?= App\Services\WorkspacePageService::MAX_TITLE ?>" aria-label="Page name" data-page-title>
        <p class="page-save-status" data-page-status role="status" aria-live="polite">All changes saved</p>
    </header>

    <?php if ($page['page_type'] === 'doc'): ?>
        <textarea class="page-doc" data-page-doc aria-label="Document" placeholder="Start writing…"><?= e((string) $page['content']) ?></textarea>

    <?php elseif ($page['page_type'] === 'sheet'): ?>
        <script type="application/json" data-page-content><?= json_encode(json_decode((string) $page['content'], true) ?: ['cells' => []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
        <div class="sheet-formula-bar"><span class="sheet-cell-name" data-sheet-ref>A1</span><input type="text" data-sheet-formula aria-label="Cell contents" placeholder="Type a value, or a formula like =SUM(A1:A5)"></div>
        <div class="sheet-scroll">
            <table class="sheet-grid" data-sheet>
                <thead><tr><th></th><?php for ($c = 0; $c < $columns; $c++): ?><th scope="col"><?= chr(65 + $c) ?></th><?php endfor; ?></tr></thead>
                <tbody>
                    <?php for ($r = 1; $r <= $rows; $r++): ?>
                        <tr><th scope="row"><?= $r ?></th><?php for ($c = 0; $c < $columns; $c++): $ref = chr(65 + $c) . $r; ?><td><input type="text" data-cell="<?= $ref ?>" aria-label="Cell <?= $ref ?>" maxlength="1000"></td><?php endfor; ?></tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <p class="widget-caption">Formulas: =SUM, =AVERAGE, =MIN, =MAX, =COUNT over ranges like A1:A5, and + − × ÷ with cell references, e.g. =B2*C2.</p>

    <?php else: ?>
        <script type="application/json" data-page-content><?= json_encode(json_decode((string) $page['content'], true) ?: ['events' => []], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
        <div class="page-cal-head">
            <button class="icon-button" type="button" data-cal-prev aria-label="Previous month" title="Previous month">‹</button>
            <h2 data-cal-month></h2>
            <button class="icon-button" type="button" data-cal-next aria-label="Next month" title="Next month">›</button>
            <button class="button-ghost page-cal-today" type="button" data-cal-today>Today</button>
        </div>
        <div class="page-cal-grid" data-cal-grid></div>
        <p class="widget-caption">Click a day to add an event. Click × to remove one.</p>
    <?php endif; ?>
</section>
<script src="/assets/js/pages.js" defer></script>
