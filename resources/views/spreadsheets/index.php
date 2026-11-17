<?php
ob_start(); ?>
    <form class="inline-form" method="post" action="/spreadsheets/save">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <button class="button button-small" type="submit">New spreadsheet</button>
    </form>
<?php
$headingActions = (string) ob_get_clean();
$headingApp = 'spreadsheets';
$headingSubtitle = 'Build tables and totals. Formulas start with = — for example =SUM(B2:B10).';
require dirname(__DIR__) . '/partials/app-heading.php';
$columnLetter = static fn (int $index): string => chr(65 + $index);
?>

<section class="app-shell-panel" aria-label="Spreadsheets">
    <aside class="app-file-list" aria-label="Your spreadsheets">
        <h2>Spreadsheets</h2>
        <?php if ($sheets === []): ?>
            <p class="app-file-empty">No spreadsheets yet.</p>
        <?php endif; ?>
        <nav>
            <?php foreach ($sheets as $item): ?>
                <?php $isCurrent = $sheet !== null && (int) $item['id'] === $sheet['id']; ?>
                <a class="app-file-link <?= $isCurrent ? 'active' : '' ?>" href="/spreadsheets?sheet=<?= (int) $item['id'] ?>" <?= $isCurrent ? 'aria-current="page"' : '' ?>><?= e($item['title']) ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="app-editor">
        <?php if ($sheet === null): ?>
            <div class="app-empty">
                <?php $iconKey = 'spreadsheets'; $iconSize = 'is-large'; require dirname(__DIR__) . '/partials/app-icon.php'; ?>
                <h2>Create your first spreadsheet</h2>
                <p>Track budgets, lists and numbers with live totals.</p>
                <form method="post" action="/spreadsheets/save">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <button class="button" type="submit">New spreadsheet</button>
                </form>
            </div>
        <?php else: ?>
            <form class="sheet-form" method="post" action="/spreadsheets/save" data-sheet-form>
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="sheet_id" value="<?= (int) $sheet['id'] ?>">
                <input type="hidden" name="cells_json" value="" data-sheet-json>
                <div class="app-toolbar">
                    <label class="sr-only" for="sheet-title">Spreadsheet name</label>
                    <input class="app-title-input" id="sheet-title" type="text" name="title" maxlength="120" value="<?= e($sheet['title']) ?>" required>
                    <span class="app-save-state" data-sheet-state>Saved</span>
                    <button class="button button-small button-secondary" type="button" data-sheet-export>Export CSV</button>
                    <button class="button button-small" type="submit">Save</button>
                </div>
                <div class="formula-bar">
                    <span class="formula-cell" data-formula-ref>A1</span>
                    <label class="sr-only" for="formula-input">Cell contents</label>
                    <input id="formula-input" type="text" maxlength="1000" data-formula-input placeholder="Type a value or a formula such as =SUM(A1:A5)" autocomplete="off">
                </div>
                <div class="sheet-scroll">
                    <table class="sheet-grid" data-sheet-grid data-columns="<?= (int) $columns ?>" data-rows="<?= (int) $rows ?>">
                        <thead>
                            <tr>
                                <th class="sheet-corner" scope="col"><span class="sr-only">Row</span></th>
                                <?php for ($c = 0; $c < $columns; $c++): ?>
                                    <th scope="col"><?= $columnLetter($c) ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($r = 1; $r <= $rows; $r++): ?>
                                <tr>
                                    <th scope="row"><?= $r ?></th>
                                    <?php for ($c = 0; $c < $columns; $c++): $ref = $columnLetter($c) . $r; ?>
                                        <td><input type="text" data-cell="<?= $ref ?>" aria-label="Cell <?= $ref ?>" maxlength="1000" value="<?= e((string) ($sheet['cells'][$ref] ?? '')) ?>" autocomplete="off" spellcheck="false"></td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <div class="app-footer-row">
                <span class="muted">Last saved <?= e($sheet['updated_at']) ?> UTC · Functions: SUM, AVERAGE, MIN, MAX, COUNT</span>
                <form method="post" action="/spreadsheets/delete" data-confirm="Delete this spreadsheet?">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="sheet_id" value="<?= (int) $sheet['id'] ?>">
                    <button class="button button-tiny button-secondary" type="submit">Delete spreadsheet</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
<script src="/assets/js/spreadsheets.js" defer></script>
