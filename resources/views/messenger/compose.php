<section class="page-heading">
    <div>
        <p class="eyebrow">MESSENGER</p>
        <h1>Compose New Message</h1>
        <p class="page-subtitle">Send a message to another Garwin AI member by their email address.</p>
    </div>
</section>

<?php if ($error !== null): ?>
    <div class="alert" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="settings-card">
    <form class="settings-form" method="post" action="/messenger/send">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <label>
            To
            <input type="email" name="to" maxlength="255" required autocomplete="off" value="<?= e($old['to']) ?>" placeholder="name@example.com">
        </label>
        <label>
            Subject
            <input type="text" name="subject" maxlength="200" required value="<?= e($old['subject']) ?>">
        </label>
        <label>
            Message
            <textarea name="body" rows="8" maxlength="10000" required><?= e($old['body']) ?></textarea>
        </label>
        <label class="checkbox-label">
            <input type="checkbox" name="priority" value="1" <?= $old['priority'] ? 'checked' : '' ?>>
            Mark as priority
        </label>
        <div class="calendar-form-actions">
            <button class="button" type="submit">Send message</button>
            <a class="text-link" href="/messenger">Cancel</a>
        </div>
    </form>
</section>
