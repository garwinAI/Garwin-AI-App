<?php
$headingActions = '<a class="button button-small button-secondary" href="/storage">Upload in Storage</a>';
$headingApp = 'documents';
$headingSubtitle = 'Read PDFs, text, Markdown, JSON and CSV files from your drive.';
require dirname(__DIR__) . '/partials/app-heading.php';
$formatSize = static fn (int $bytes): string => $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
$kindLabel = ['pdf' => 'PDF', 'csv' => 'CSV', 'text' => 'TXT'];
?>

<?php if ($items === []): ?>
    <section class="app-empty app-empty-card">
        <?php $iconKey = 'documents'; $iconSize = 'is-large'; require dirname(__DIR__) . '/partials/app-icon.php'; ?>
        <h2>No documents yet</h2>
        <p>Upload PDF, TXT, Markdown, JSON or CSV files to any folder in Storage and read them here.</p>
        <a class="button" href="/storage">Go to Storage</a>
    </section>
<?php else: ?>
    <section class="app-shell-panel" aria-label="Document Viewer">
        <aside class="app-file-list" aria-label="Your documents">
            <h2>Documents</h2>
            <nav>
                <?php foreach ($items as $item): ?>
                    <?php $isCurrent = $item['folder'] === $selected['folder'] && $item['name'] === $selected['name']; ?>
                    <a class="app-file-link code-file-link <?= $isCurrent ? 'active' : '' ?>" href="/documents?<?= e(http_build_query(['folder' => $item['folder'], 'file' => $item['name']])) ?>" <?= $isCurrent ? 'aria-current="page"' : '' ?>>
                        <span class="code-ext ext-<?= e($item['kind']) ?>"><?= e(strtoupper(pathinfo($item['name'], PATHINFO_EXTENSION))) ?></span>
                        <span class="nav-sublink-label"><?= e($item['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <div class="app-editor">
            <div class="app-toolbar">
                <div class="doc-title">
                    <strong><?= e($selected['name']) ?></strong>
                    <span><?= e($selected['folder']) ?> · <?= e($formatSize($selected['size'])) ?> · <?= e(gmdate('M j, Y', $selected['modified_at'])) ?></span>
                </div>
                <?php if ($selected['kind'] === 'pdf'): ?>
                    <a class="button button-small button-secondary" href="<?= e($selected['url']) ?>" target="_blank" rel="noopener">Open in new tab</a>
                <?php endif; ?>
                <a class="button button-small button-secondary" href="/storage/download?<?= e(http_build_query(['folder' => $selected['folder'], 'file' => $selected['name']])) ?>">Download</a>
            </div>
            <div class="doc-view">
                <?php if ($selected['kind'] === 'pdf'): ?>
                    <iframe class="doc-pdf" src="<?= e($selected['url']) ?>" title="<?= e($selected['name']) ?>"></iframe>
                <?php elseif ($tooLarge): ?>
                    <p class="doc-message">This file is larger than 1 MB, so it can't be previewed. Download it to open it.</p>
                <?php elseif ($text === null): ?>
                    <p class="doc-message">This file couldn't be read. It may have been moved or deleted.</p>
                <?php elseif ($table !== null && $table !== []): ?>
                    <div class="doc-table-wrap">
                        <table class="doc-table">
                            <thead><tr><?php foreach ($table[0] as $cell): ?><th scope="col"><?= e($cell) ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($table, 1) as $row): ?>
                                    <tr><?php foreach ($row as $cell): ?><td><?= e($cell) ?></td><?php endforeach; ?></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (count($table) >= 500): ?><p class="muted">Showing the first 500 rows.</p><?php endif; ?>
                <?php else: ?>
                    <pre class="doc-text"><?= e($text) ?></pre>
                <?php endif; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
