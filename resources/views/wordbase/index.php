<section class="page-heading">
    <div>
        <p class="eyebrow">PRODUCTIVITY</p>
        <h1>Wordbase</h1>
        <p class="page-subtitle">Draft notes, ideas, and project writing in a private workspace.</p>
    </div>
    <form class="inline-form" method="post" action="/wordbase/save">
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
                    <div class="history-item">
                        <a class="assistant-history-link <?= $document !== null && (int) $document['id'] === (int) $item['id'] ? 'active' : '' ?>"
                           href="/wordbase?document=<?= (int) $item['id'] ?>"
                           <?= $document !== null && (int) $document['id'] === (int) $item['id'] ? 'aria-current="page"' : '' ?>>
                            <?= e($item['title']) ?>
                        </a>
                        <form class="inline-form" method="post" action="/wordbase/delete">
                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="document_id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="button button-tiny button-secondary">Delete</button>
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
            <form class="wordbase-editor" method="post" action="/wordbase/save">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>">
                <label>
                    <span class="sr-only">Document title</span>
                    <input type="text" name="title" value="<?= e($document['title']) ?>" maxlength="255" class="wordbase-title" placeholder="Untitled document">
                </label>
                <textarea class="wordbase-body" name="content" rows="18" placeholder="Write something meaningful…"><?= e($document['content']) ?></textarea>
                <div class="wordbase-editor-footer">
                    <span class="muted">Last updated: <?= e($document['updated_at']) ?></span>
                    <button class="button" type="submit">Save changes</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
