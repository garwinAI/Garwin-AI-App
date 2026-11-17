<?php
$headingActions = '';
$headingApp = 'codebase';
$headingSubtitle = 'Keep scripts, snippets and config files in one private place.';
require dirname(__DIR__) . '/partials/app-heading.php';
?>

<section class="app-shell-panel" aria-label="Codebase">
    <aside class="app-file-list" aria-label="Your code files">
        <h2>Files</h2>
        <form class="new-file-form" method="post" action="/codebase/save">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <label class="sr-only" for="new-file-name">New file name</label>
            <input id="new-file-name" type="text" name="name" maxlength="120" placeholder="new-file.js" required pattern="[A-Za-z0-9][A-Za-z0-9._\- ]*">
            <button class="button button-tiny" type="submit">Add</button>
        </form>
        <?php if ($files === []): ?>
            <p class="app-file-empty">No files yet. Name one above, for example index.html.</p>
        <?php endif; ?>
        <nav>
            <?php foreach ($files as $item): ?>
                <?php $isCurrent = $file !== null && (int) $item['id'] === $file['id']; ?>
                <a class="app-file-link code-file-link <?= $isCurrent ? 'active' : '' ?>" href="/codebase?file=<?= (int) $item['id'] ?>" <?= $isCurrent ? 'aria-current="page"' : '' ?>>
                    <span class="code-ext"><?= e(strtoupper(pathinfo($item['name'], PATHINFO_EXTENSION) ?: 'TXT')) ?></span>
                    <span class="nav-sublink-label"><?= e($item['name']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <div class="app-editor">
        <?php if ($file === null): ?>
            <div class="app-empty">
                <?php $iconKey = 'codebase'; $iconSize = 'is-large'; require dirname(__DIR__) . '/partials/app-icon.php'; ?>
                <h2>Start your codebase</h2>
                <p>Add a file on the left. The language is picked from the file extension.</p>
            </div>
        <?php else: ?>
            <form class="code-form" method="post" action="/codebase/save" data-code-form>
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="file_id" value="<?= (int) $file['id'] ?>">
                <div class="app-toolbar">
                    <label class="sr-only" for="code-name">File name</label>
                    <input class="app-title-input is-mono" id="code-name" type="text" name="name" maxlength="120" value="<?= e($file['name']) ?>" required>
                    <span class="code-language"><?= e($file['language']) ?></span>
                    <span class="app-save-state" data-code-state>Saved</span>
                    <button class="button button-small button-secondary" type="button" data-code-download>Download</button>
                    <button class="button button-small" type="submit">Save</button>
                </div>
                <div class="code-editor">
                    <pre class="code-gutter" aria-hidden="true" data-code-gutter>1</pre>
                    <label class="sr-only" for="code-content">Code</label>
                    <textarea id="code-content" name="content" spellcheck="false" autocapitalize="off" autocomplete="off" wrap="off" data-code-input><?= e($file['content']) ?></textarea>
                </div>
            </form>
            <div class="app-footer-row">
                <span class="muted" data-code-status>Last saved <?= e($file['updated_at']) ?> UTC · Tab indents · Ctrl/⌘+S saves</span>
                <form method="post" action="/codebase/delete" data-confirm="Delete <?= e($file['name']) ?>?">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="file_id" value="<?= (int) $file['id'] ?>">
                    <button class="button button-tiny button-secondary" type="submit">Delete file</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
<script src="/assets/js/codebase.js" defer></script>
