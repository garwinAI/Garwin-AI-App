<section class="page-heading">
    <div>
        <p class="eyebrow">YOUR WORKSPACE</p>
        <h1>Calendar</h1>
        <p class="page-subtitle">Plan and manage your events. All times are shown in UTC.</p>
    </div>
    <a class="button button-small" href="#event-form">New event</a>
</section>

<?php if ($flashMessage !== null): ?>
    <div class="admin-alert <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
<?php endif; ?>

<section class="calendar-panel" aria-label="<?= e($monthLabel) ?> calendar">
    <div class="calendar-toolbar">
        <a class="button button-small button-secondary" href="/calendar?month=<?= e($previousMonth) ?>" aria-label="Previous month">‹</a>
        <h2><?= e($monthLabel) ?></h2>
        <a class="button button-small button-secondary" href="/calendar?month=<?= e($nextMonth) ?>" aria-label="Next month">›</a>
    </div>
    <div class="calendar-grid" role="grid" aria-label="<?= e($monthLabel) ?>">
        <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $weekday): ?>
            <div class="calendar-weekday" role="columnheader"><?= e($weekday) ?></div>
        <?php endforeach; ?>
        <?php foreach ($calendarDays as $day): ?>
            <div class="calendar-day <?= $day['in_month'] ? '' : 'outside-month' ?>" role="gridcell" aria-label="<?= e($day['date']) ?>">
                <time class="calendar-day-number" datetime="<?= e($day['date']) ?>"><?= e($day['day']) ?></time>
                <?php foreach ($day['events'] as $event): ?>
                    <a class="calendar-event" href="/calendar?month=<?= e($month) ?>&amp;edit=<?= e((string) $event['id']) ?>#event-form">
                        <time datetime="<?= e(str_replace(' ', 'T', $event['starts_at'])) ?>Z"><?= e(substr($event['starts_at'], 11, 5)) ?></time>
                        <span><?= e($event['title']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="settings-card calendar-form-card" id="event-form">
    <div class="calendar-form-heading">
        <div>
            <p class="eyebrow"><?= $editingEvent === null ? 'PLAN AHEAD' : 'EDIT EVENT' ?></p>
            <h2><?= $editingEvent === null ? 'Create an event' : 'Update event' ?></h2>
        </div>
        <?php if ($editingEvent !== null): ?>
            <a class="text-link" href="/calendar?month=<?= e($month) ?>">Cancel</a>
        <?php endif; ?>
    </div>
    <form class="settings-form" method="post" action="/calendar/events">
        <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="month" value="<?= e($month) ?>">
        <input type="hidden" name="event_id" value="<?= e((string) ($editingEvent['id'] ?? '')) ?>">
        <label>
            Event title
            <input type="text" name="title" maxlength="120" required value="<?= e((string) ($editingEvent['title'] ?? '')) ?>">
        </label>
        <label>
            Description
            <textarea name="description" rows="3" maxlength="2000"><?= e((string) ($editingEvent['description'] ?? '')) ?></textarea>
        </label>
        <div class="calendar-datetime-fields">
            <label>
                Starts (UTC)
                <input type="datetime-local" name="starts_at" required value="<?= e(isset($editingEvent['starts_at']) ? str_replace(' ', 'T', substr($editingEvent['starts_at'], 0, 16)) : '') ?>">
            </label>
            <label>
                Ends (UTC)
                <input type="datetime-local" name="ends_at" required value="<?= e(isset($editingEvent['ends_at']) ? str_replace(' ', 'T', substr($editingEvent['ends_at'], 0, 16)) : '') ?>">
            </label>
        </div>
        <div class="calendar-form-actions">
            <button class="button" type="submit"><?= $editingEvent === null ? 'Create event' : 'Save changes' ?></button>
        </div>
    </form>
    <?php if ($editingEvent !== null): ?>
        <form class="calendar-delete-form" method="post" action="/calendar/events/delete" data-confirm="Delete this event?">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="month" value="<?= e($month) ?>">
            <input type="hidden" name="event_id" value="<?= e((string) $editingEvent['id']) ?>">
            <button class="button button-danger" type="submit">Delete event</button>
        </form>
    <?php endif; ?>
</section>
