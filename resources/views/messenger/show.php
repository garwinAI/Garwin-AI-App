<section class="page-heading">
    <div>
        <p class="eyebrow">MESSENGER<?= (int) $message['is_priority'] === 1 ? ' · PRIORITY' : '' ?></p>
        <h1><?= e($message['subject']) ?></h1>
        <p class="page-subtitle">From <?= e(trim($message['sender_first_name'] . ' ' . $message['sender_last_name'])) ?> (<?= e($message['sender_email']) ?>) · <?= e(gmdate('M j, Y g:i A', strtotime($message['created_at'] . ' UTC'))) ?> UTC</p>
    </div>
    <a class="button button-small" href="/messenger/compose?to=<?= rawurlencode($message['sender_email']) ?>&amp;subject=<?= rawurlencode('Re: ' . $message['subject']) ?>">Reply</a>
</section>

<section class="settings-card messenger-body"><?= nl2br(e($message['body'])) ?></section>
<p><a class="text-link" href="/messenger">← Back to messages</a></p>
