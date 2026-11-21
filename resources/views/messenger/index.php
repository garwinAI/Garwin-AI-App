<section class="page-heading">
    <div>
        <p class="eyebrow">MESSENGER</p>
        <h1><?= e($viewLabel) ?> messages</h1>
        <p class="page-subtitle">Messages from other Garwin AI members.</p>
    </div>
    <a class="button button-small" href="/messenger/compose">Compose New Message</a>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>

<nav class="messenger-tabs" aria-label="Message views">
    <a class="<?= $view === 'new' ? 'active' : '' ?>" href="/messenger?view=new" <?= $view === 'new' ? 'aria-current="page"' : '' ?>>New</a>
    <a class="<?= $view === 'read' ? 'active' : '' ?>" href="/messenger?view=read" <?= $view === 'read' ? 'aria-current="page"' : '' ?>>Read</a>
    <a class="<?= $view === 'priority' ? 'active' : '' ?>" href="/messenger?view=priority" <?= $view === 'priority' ? 'aria-current="page"' : '' ?>>Priority</a>
</nav>

<section class="settings-card messenger-list">
    <?php if ($messages === []): ?>
        <p class="page-subtitle">No <?= e(strtolower($viewLabel)) ?> messages.</p>
    <?php else: ?>
        <?php foreach ($messages as $message): ?>
            <a class="messenger-row <?= $message['read_at'] === null ? 'unread' : '' ?>" href="/messenger/message?id=<?= e((string) $message['id']) ?>">
                <span class="messenger-from"><?= e(trim($message['sender_first_name'] . ' ' . $message['sender_last_name'])) ?></span>
                <span class="messenger-subject">
                    <?php if ((int) $message['is_priority'] === 1): ?><span class="messenger-priority">Priority</span><?php endif; ?>
                    <?= e($message['subject']) ?>
                </span>
                <time class="messenger-date" datetime="<?= e($message['created_at']) ?>"><?= e(gmdate('M j, Y', strtotime($message['created_at'] . ' UTC'))) ?></time>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
