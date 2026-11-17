<section class="page-heading">
    <div>
        <p class="eyebrow">WORKSPACE</p>
        <h1>AI Assistant</h1>
        <p class="page-subtitle">Ask a question or continue a conversation.</p>
    </div>
    <a class="button button-small" href="/assistant">New conversation</a>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="alert" role="status"><?= e($flashMessage['message'] ?? '') ?></div>
<?php endif; ?>

<?php if ($budgetStatus !== null): ?>
    <div class="panel budget-panel">
        <div class="budget-panel-row">
            <strong>AI budget</strong>
            <span>$<?= number_format((int) $budgetStatus['spent_cents'] / 100, 2) ?> / $<?= number_format((int) $budgetStatus['budget_cents'] / 100, 2) ?></span>
        </div>
        <progress max="<?= e((string) $budgetStatus['budget_cents']) ?>" value="<?= e((string) min((int) $budgetStatus['budget_cents'], (int) $budgetStatus['spent_cents'])) ?>" class="meter"></progress>
    </div>
<?php endif; ?>

<section class="assistant-shell" aria-label="AI Assistant">
    <aside class="assistant-history" aria-label="Conversation history">
        <h2>Recent conversations</h2>
        <form class="history-search" method="get" action="/assistant">
            <label class="sr-only" for="assistant-search">Search history</label>
            <input id="assistant-search" type="search" name="search" value="<?= e($searchTerm ?? '') ?>" placeholder="Search history">
        </form>
        <?php if ($conversations === []): ?>
            <p class="assistant-history-empty">Your conversations will appear here.</p>
        <?php else: ?>
            <nav>
                <?php foreach ($conversations as $item): ?>
                    <div class="history-item">
                        <a class="assistant-history-link <?= $conversation !== null && (int) $conversation['id'] === (int) $item['id'] ? 'active' : '' ?>"
                           href="/assistant?conversation=<?= (int) $item['id'] ?>"
                           <?= $conversation !== null && (int) $conversation['id'] === (int) $item['id'] ? 'aria-current="page"' : '' ?>>
                            <?= e($item['title']) ?>
                        </a>
                        <form class="inline-form" method="post" action="/assistant/delete">
                            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                            <input type="hidden" name="conversation_id" value="<?= (int) $item['id'] ?>">
                            <button type="submit" class="button button-tiny button-secondary">Delete</button>
                        </form>
                    </div>
                    <form class="history-rename" method="post" action="/assistant/rename">
                        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="conversation_id" value="<?= (int) $item['id'] ?>">
                        <input type="text" name="title" value="<?= e($item['title']) ?>" maxlength="80" aria-label="Conversation title">
                        <button type="submit" class="button button-tiny button-secondary">Rename</button>
                    </form>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </aside>

    <div class="assistant-conversation">
        <?php if ($conversation === null && $messages === []): ?>
            <div class="assistant-welcome">
                <span class="feature-icon" aria-hidden="true">✦</span>
                <h2>What can I help with?</h2>
                <p>Your chats are saved privately to your account.</p>
            </div>
        <?php else: ?>
            <div class="assistant-messages" aria-live="polite">
                <?php foreach ($messages as $message): ?>
                    <article class="assistant-message <?= $message['role'] === 'user' ? 'from-user' : 'from-assistant' ?>">
                        <span class="assistant-message-label"><?= $message['role'] === 'user' ? 'You' : 'GarwinAI' ?></span>
                        <p><?= nl2br(e($message['content']), false) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="assistant-composer" method="post" action="/assistant/send">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <?php if ($conversation !== null): ?>
                <input type="hidden" name="conversation_id" value="<?= (int) $conversation['id'] ?>">
            <?php endif; ?>
            <label class="sr-only" for="assistant-message">Your message</label>
            <textarea id="assistant-message" name="message" rows="3" maxlength="4000"
                      placeholder="Message GarwinAI…" required></textarea>
            <div class="assistant-composer-footer">
                <span>Up to 4,000 characters</span>
                <button class="button button-small" type="submit">Send message</button>
            </div>
        </form>
    </div>
</section>
