<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Csrf;
use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class CalendarController
{
    private const UTC = 'UTC';

    public function index(): void
    {
        $user = $this->requireWorkspaceUser();
        $month = $this->month($_GET['month'] ?? gmdate('Y-m'));
        if ($month === null) {
            redirect('/calendar');
        }

        $monthStart = DateTimeImmutable::createFromFormat('!Y-m', $month, new DateTimeZone(self::UTC));
        if ($monthStart === false) {
            throw new \RuntimeException('Unable to parse the selected calendar month.');
        }
        $monthEnd = $monthStart->modify('+1 month');
        $firstWeekday = (int) $monthStart->format('N') - 1;
        $gridStart = $monthStart->modify('-' . $firstWeekday . ' days');
        $calendarDays = [];
        for ($offset = 0; $offset < 42; $offset++) {
            $date = $gridStart->modify('+' . $offset . ' days');
            $calendarDays[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('j'),
                'in_month' => $date->format('Y-m') === $month,
                'events' => [],
            ];
        }

        $eventsStatement = app_db()->prepare(
            'SELECT id, title, description, starts_at, ends_at
             FROM calendar_events
             WHERE user_id = :user_id AND starts_at >= :month_start AND starts_at < :month_end
             ORDER BY starts_at, id'
        );
        $eventsStatement->execute([
            'user_id' => $user['id'],
            'month_start' => $monthStart->format('Y-m-d H:i:s'),
            'month_end' => $monthEnd->format('Y-m-d H:i:s'),
        ]);
        $events = $eventsStatement->fetchAll(PDO::FETCH_ASSOC);
        $eventsByDate = [];
        foreach ($events as $event) {
            $eventsByDate[substr($event['starts_at'], 0, 10)][] = $event;
        }
        foreach ($calendarDays as &$day) {
            $day['events'] = $eventsByDate[$day['date']] ?? [];
        }
        unset($day);

        $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
        $editingEvent = null;
        if ($editId !== false && $editId !== null) {
            $editStatement = app_db()->prepare(
                'SELECT id, title, description, starts_at, ends_at
                 FROM calendar_events WHERE id = :id AND user_id = :user_id'
            );
            $editStatement->execute(['id' => $editId, 'user_id' => $user['id']]);
            $editingEvent = $editStatement->fetch(PDO::FETCH_ASSOC);
            if ($editingEvent === false) {
                set_flash_message('error', 'That calendar event is not available.');
                redirect('/calendar?month=' . rawurlencode($month));
            }
        }

        render('calendar/index', [
            'title' => 'Calendar',
            'user' => $user,
            'month' => $month,
            'monthLabel' => $monthStart->format('F Y'),
            'previousMonth' => $monthStart->modify('-1 month')->format('Y-m'),
            'nextMonth' => $monthStart->modify('+1 month')->format('Y-m'),
            'calendarDays' => $calendarDays,
            'editingEvent' => $editingEvent,
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function save(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }

        $month = $this->month($_POST['month'] ?? gmdate('Y-m')) ?? gmdate('Y-m');
        $titleInput = $_POST['title'] ?? '';
        $descriptionInput = $_POST['description'] ?? '';
        $startsInput = $_POST['starts_at'] ?? '';
        $endsInput = $_POST['ends_at'] ?? '';
        $eventIdInput = $_POST['event_id'] ?? '';
        if (!is_string($titleInput) || !is_string($descriptionInput) || !is_string($startsInput)
            || !is_string($endsInput) || !is_string($eventIdInput)) {
            $this->invalidEvent($month);
        }

        $title = trim($titleInput);
        $description = trim($descriptionInput);
        $startsAt = $this->parseDateTime($startsInput);
        $endsAt = $this->parseDateTime($endsInput);
        if ($title === '' || mb_strlen($title) > 120 || mb_strlen($description) > 2000
            || preg_match('/[\x00-\x1F\x7F]/', $title)
            || $startsAt === null || $endsAt === null || $endsAt <= $startsAt) {
            $this->invalidEvent($month);
        }

        $pdo = app_db();
        $now = gmdate('Y-m-d H:i:s');
        $values = [
            'title' => $title,
            'description' => $description,
            'starts_at' => $startsAt->format('Y-m-d H:i:s'),
            'ends_at' => $endsAt->format('Y-m-d H:i:s'),
            'updated_at' => $now,
        ];
        if ($eventIdInput === '') {
            $insert = $pdo->prepare(
                'INSERT INTO calendar_events
                 (user_id, title, description, starts_at, ends_at, created_at, updated_at)
                 VALUES (:user_id, :title, :description, :starts_at, :ends_at, :created_at, :updated_at)'
            );
            $insert->execute($values + ['user_id' => $user['id'], 'created_at' => $now]);
            set_flash_message('success', 'Event created.');
        } else {
            $eventId = filter_var($eventIdInput, FILTER_VALIDATE_INT);
            if ($eventId === false || $eventId < 1) {
                $this->invalidEvent($month);
            }
            $existing = $pdo->prepare('SELECT 1 FROM calendar_events WHERE id = :id AND user_id = :user_id');
            $existing->execute(['id' => $eventId, 'user_id' => $user['id']]);
            if ($existing->fetchColumn() === false) {
                set_flash_message('error', 'That calendar event is not available.');
                redirect('/calendar?month=' . rawurlencode($month));
            }
            $update = $pdo->prepare(
                'UPDATE calendar_events
                 SET title = :title, description = :description, starts_at = :starts_at,
                     ends_at = :ends_at, updated_at = :updated_at
                 WHERE id = :id AND user_id = :user_id'
            );
            $update->execute($values + ['id' => $eventId, 'user_id' => $user['id']]);
            set_flash_message('success', 'Event updated.');
        }

        redirect('/calendar?month=' . rawurlencode($month));
    }

    public function delete(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }

        $month = $this->month($_POST['month'] ?? gmdate('Y-m')) ?? gmdate('Y-m');
        $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);
        if ($eventId === false || $eventId === null || $eventId < 1) {
            set_flash_message('error', 'Choose a valid calendar event.');
            redirect('/calendar?month=' . rawurlencode($month));
        }
        $delete = app_db()->prepare('DELETE FROM calendar_events WHERE id = :id AND user_id = :user_id');
        $delete->execute(['id' => $eventId, 'user_id' => $user['id']]);
        if ($delete->rowCount() !== 1) {
            set_flash_message('error', 'That calendar event is not available.');
        } else {
            set_flash_message('success', 'Event deleted.');
        }
        redirect('/calendar?month=' . rawurlencode($month));
    }

    private function month(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/\A\d{4}-\d{2}\z/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m', $value, new DateTimeZone(self::UTC));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m') !== $value) {
            return null;
        }

        return $value;
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        if (!preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $value)) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, new DateTimeZone(self::UTC));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d\TH:i') !== $value) {
            return null;
        }

        return $date;
    }

    private function invalidEvent(string $month): never
    {
        set_flash_message('error', 'Enter a title, valid UTC times with the end after the start, and a description under 2,000 characters.');
        redirect('/calendar?month=' . rawurlencode($month) . '#event-form');
    }

    private function requireWorkspaceUser(): array
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }

        return $user;
    }

    private function validateCsrf(): bool
    {
        $token = $_POST['_token'] ?? null;
        if (Csrf::isValid(is_string($token) ? $token : null)) {
            return true;
        }
        render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);

        return false;
    }
}
