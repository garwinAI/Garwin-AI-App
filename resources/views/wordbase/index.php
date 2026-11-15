<section class="page-heading">
    <div>
        <p class="eyebrow">PRODUCTIVITY</p>
        <h1>Wordbase</h1>
        <p class="page-subtitle">Draft notes, ideas, and project writing in a private workspace.</p>
    </div>
    <form method="post" action="/wordbase/save" style="display:inline;">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <button class="button button-small" type="submit">New document</button>
    </form>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="alert" role="status"><?= e($flashMessage['message'] ?? '') ?></div>
<?php endif; ?>

<section class="assistant-shell" aria-label="Wordbase">
    <aside class="assistant-history" aria-label="Wordbase documents">
        <h2>Documents</h2>
        <?php if ($documents === []): ?>
            <p class="assistant-history-empty">Create your first document to get started.</p>
        <?php else: ?>
            <nav>
                <?php foreach ($documents as $item): ?>
                    <div style="display:flex; align-items:center; gap:8px; margin:4px 0;">
                        <a class="assistant-history-link <?= $document !== null && (int) $document['id'] === (int) $item['id'] ? 'active' : '' ?>"
                           href="/wordbase?document=<?= (int) $item['id'] ?>"
                           <?= $document !== null && (int) $document['id'] === (int) $item['id'] ? 'aria-current="page"' : '' ?>
                           style="flex:1; min-width:0;">
                            <?= e($item['title']) ?>
                        </a>
                        <form method="post" action="/wordbase/delete" style="display:inline;">
                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="document_id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="button button-small" style="padding:4px 8px; font-size:11px;">Delete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </aside>

    <div class="assistant-conversation">
        <?php if ($document === null): ?>
            <div class="assistant-welcome">
                <span class="feature-icon" aria-hidden="true">✎</span>
                <h2>Start writing</h2>
                <p>Create a new document and capture ideas, plans, and notes.</p>
            </div>
        <?php else: ?>
            <form method="post" action="/wordbase/save" style="display:flex; flex-direction:column; gap:12px;">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">
                <label>
                    <span class="sr-only">Document title</span>
                    <input type="text" name="title" value="<?= e($document['title']) ?>" maxlength="255" style="width:100%; padding:12px 14px; border:1px solid #d8dce2; border-radius:10px; font-size:1.05rem;" placeholder="Untitled document">
                </label>
                <textarea name="content" rows="18" style="width:100%; min-height:320px; resize:vertical; padding:14px; border:1px solid #d8dce2; border-radius:12px; font:inherit; line-height:1.6;" placeholder="Write something meaningful…"><?= e($document['content']) ?></textarea>
                <div style="display:flex; justify-content:space-between; gap:12px; align-items:center; flex-wrap:wrap;">
                    <span class="muted">Last updated: <?= e($document['updated_at']) ?></span>
                    <button class="button" type="submit">Save changes</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
