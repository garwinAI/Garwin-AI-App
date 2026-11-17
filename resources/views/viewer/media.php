<?php
$headingActions = '<a class="button button-small button-secondary" href="/storage">Upload in Storage</a>';
$headingApp = 'media';
$headingSubtitle = 'Photos, videos and audio from your drive, all in one place.';
require dirname(__DIR__) . '/partials/app-heading.php';
$formatSize = static fn (int $bytes): string => $bytes >= 1048576 ? number_format($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
$filters = ['all' => 'All', 'image' => 'Photos', 'video' => 'Videos', 'audio' => 'Audio'];
?>

<nav class="settings-tabs" aria-label="Media type">
    <?php foreach ($filters as $key => $label): ?>
        <a href="/media<?= $key === 'all' ? '' : '?type=' . $key ?>" class="<?= $filter === $key ? 'selected' : '' ?>" <?= $filter === $key ? 'aria-current="page"' : '' ?>><?= e($label) ?> <span class="tab-count"><?= (int) $counts[$key] ?></span></a>
    <?php endforeach; ?>
</nav>

<?php if ($items === []): ?>
    <section class="app-empty app-empty-card">
        <?php $iconKey = 'media'; $iconSize = 'is-large'; require dirname(__DIR__) . '/partials/app-icon.php'; ?>
        <h2>No media yet</h2>
        <p>Upload photos (JPG, PNG, GIF, WebP), videos (MP4, WebM) or audio (MP3, WAV, M4A) to any folder in Storage and they appear here.</p>
        <a class="button" href="/storage">Go to Storage</a>
    </section>
<?php else: ?>
    <section class="viewer-stage" aria-label="Preview">
        <div class="viewer-stage-media">
            <?php if ($selected['kind'] === 'image'): ?>
                <img src="<?= e($selected['url']) ?>" alt="<?= e($selected['name']) ?>">
            <?php elseif ($selected['kind'] === 'video'): ?>
                <video src="<?= e($selected['url']) ?>" controls preload="metadata"></video>
            <?php else: ?>
                <div class="viewer-audio">
                    <span class="viewer-audio-icon" aria-hidden="true">♪</span>
                    <audio src="<?= e($selected['url']) ?>" controls preload="metadata"></audio>
                </div>
            <?php endif; ?>
        </div>
        <div class="viewer-stage-meta">
            <div>
                <strong><?= e($selected['name']) ?></strong>
                <span><?= e($selected['folder']) ?> · <?= e($formatSize($selected['size'])) ?> · <?= e(gmdate('M j, Y', $selected['modified_at'])) ?></span>
            </div>
            <a class="button button-small button-secondary" href="/storage/download?<?= e(http_build_query(['folder' => $selected['folder'], 'file' => $selected['name']])) ?>">Download</a>
        </div>
    </section>

    <section class="media-grid" aria-label="Your media">
        <?php foreach ($items as $item): ?>
            <?php $isSelected = $item['folder'] === $selected['folder'] && $item['name'] === $selected['name']; ?>
            <a class="media-thumb <?= $isSelected ? 'active' : '' ?>" href="/media?<?= e(http_build_query(array_filter(['type' => $filter === 'all' ? null : $filter, 'folder' => $item['folder'], 'file' => $item['name']]))) ?>" <?= $isSelected ? 'aria-current="true"' : '' ?>>
                <span class="media-thumb-frame kind-<?= e($item['kind']) ?>">
                    <?php if ($item['kind'] === 'image'): ?>
                        <img src="<?= e($item['url']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span aria-hidden="true"><?= $item['kind'] === 'video' ? '▶' : '♪' ?></span>
                    <?php endif; ?>
                </span>
                <span class="media-thumb-name"><?= e($item['name']) ?></span>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
