<section class="page-heading">
    <div>
        <p class="eyebrow">YOUR WORKSPACE</p>
        <h1>Storage</h1>
        <p class="page-subtitle"><?= number_format((int) $account['used_bytes'] / 1073741824, 2) ?> GB of <?= number_format((int) $account['quota_bytes'] / 1073741824, 0) ?> GB used</p>
    </div>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>

<section class="storage-browser">
    <nav class="storage-folder-nav" aria-label="Storage folders">
        <a class="storage-folder-link <?= $selectedFolder === '' ? 'selected' : '' ?>" href="/storage">My Drive</a>
        <?php foreach ($folders as $folder): ?>
            <a class="storage-folder-link <?= $selectedFolder === $folder ? 'selected' : '' ?>" href="/storage?folder=<?= rawurlencode($folder) ?>"><?= e($folder) ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="storage-content">
        <div class="storage-toolbar">
            <div class="storage-breadcrumb">
                <a href="/storage">My Drive</a>
                <?php if ($selectedFolder !== ''): ?><span aria-hidden="true">/</span><strong><?= e($selectedFolder) ?></strong><?php endif; ?>
            </div>
            <form class="storage-search" method="get" action="/storage">
                <?php if ($selectedFolder !== ''): ?><input type="hidden" name="folder" value="<?= e($selectedFolder) ?>"><?php endif; ?>
                <label class="visually-hidden" for="storage-search">Search storage</label>
                <input id="storage-search" type="search" name="q" value="<?= e($searchQuery) ?>" placeholder="Search in storage">
                <button class="button button-small" type="submit">Search</button>
            </form>
            <div class="storage-view-toggle">Showing <?= e(ucfirst($storageView)) ?> view · <a href="/settings">Change in Settings</a></div>
        </div>

        <?php if ($selectedFolder === ''): ?>
            <form class="storage-create-folder" method="post" action="/storage/folders">
                <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                <label for="new-folder-name">New folder</label>
                <input id="new-folder-name" type="text" name="name" maxlength="64" pattern="[A-Za-z0-9 _-]+" placeholder="Folder name" required>
                <button class="button button-small" type="submit">Create folder</button>
            </form>
            <?php if ($folders === []): ?>
                <p class="storage-empty">Your Drive is empty.</p>
            <?php else: ?>
                <div class="storage-items <?= $storageView === 'list' ? 'is-list' : 'is-grid' ?>">
                    <?php foreach ($folders as $folder): ?>
                        <a class="storage-item storage-folder-item" href="/storage?folder=<?= rawurlencode($folder) ?>">
                            <span class="storage-item-icon" aria-hidden="true">▰</span>
                            <span class="storage-item-name"><?= e($folder) ?></span>
                            <span class="storage-item-meta">Folder</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="storage-folder-actions">
                <form class="storage-upload-form" method="post" action="/storage/upload" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="folder" value="<?= e($selectedFolder) ?>">
                    <label for="storage-upload">Upload to <?= e($selectedFolder) ?></label>
                    <input id="storage-upload" type="file" name="upload" required>
                    <span class="field-hint">Maximum file size: 100 MB.</span>
                    <button class="button button-small" type="submit">Upload</button>
                </form>
                <?php if (!in_array($selectedFolder, \App\Services\StorageProvisioner::DEFAULT_FOLDERS, true) && $folderIsEmpty): ?>
                    <form method="post" action="/storage/folders/delete">
                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="name" value="<?= e($selectedFolder) ?>">
                        <button class="button button-small button-danger" type="submit">Delete empty folder</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php if ($contents === []): ?>
                <p class="storage-empty"><?= $searchQuery !== '' ? 'No matching items.' : 'This folder is empty.' ?></p>
            <?php else: ?>
                <div class="storage-items <?= $storageView === 'list' ? 'is-list' : 'is-grid' ?>">
                    <?php foreach ($contents as $entry): ?>
                        <?php if ($entry['type'] === 'folder'): ?>
                            <div class="storage-item">
                                <span class="storage-item-icon" aria-hidden="true">▰</span>
                                <span class="storage-item-name"><?= e($entry['name']) ?></span>
                                <span class="storage-item-meta">Folder</span>
                            </div>
                        <?php else: ?>
                            <div class="storage-item">
                                <span class="storage-item-icon" aria-hidden="true">▤</span>
                                <a class="storage-item-name" href="/storage/download?folder=<?= rawurlencode($selectedFolder) ?>&amp;file=<?= rawurlencode($entry['name']) ?>"><?= e($entry['name']) ?></a>
                                <span class="storage-item-meta"><?= number_format((int) $entry['size']) ?> bytes · <?= e(gmdate('M j, Y', (int) $entry['modified_at'])) ?></span>
                                <form method="post" action="/storage/delete" data-confirm="Delete this file?">
                                    <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                                    <input type="hidden" name="folder" value="<?= e($selectedFolder) ?>">
                                    <input type="hidden" name="file" value="<?= e($entry['name']) ?>">
                                    <button class="icon-button storage-delete-button" type="submit" aria-label="Delete <?= e($entry['name']) ?>" title="Delete file">×</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
