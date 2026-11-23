<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Csrf;
use PDO;

/** Internal messages between Garwin AI members. */
final class MessengerController
{
    private const VIEWS = [
        'new' => 'New',
        'read' => 'Read',
        'priority' => 'Priority',
    ];

    public function index(): void
    {
        $user = $this->requireWorkspaceUser();
        $viewInput = $_GET['view'] ?? 'new';
        $view = is_string($viewInput) && isset(self::VIEWS[$viewInput]) ? $viewInput : 'new';
        $filter = match ($view) {
            'read' => 'm.read_at IS NOT NULL',
            'priority' => 'm.is_priority = 1',
            default => 'm.read_at IS NULL',
        };

        $statement = app_db()->prepare(
            "SELECT m.id, m.subject, m.is_priority, m.read_at, m.created_at,
                    s.first_name AS sender_first_name, s.last_name AS sender_last_name, s.email AS sender_email
             FROM messages m JOIN users s ON s.id = m.sender_user_id
             WHERE m.recipient_user_id = :user_id AND {$filter}
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT 100"
        );
        $statement->execute(['user_id' => $user['id']]);

        render('messenger/index', [
            'title' => 'Messenger',
            'user' => $user,
            'view' => $view,
            'viewLabel' => self::VIEWS[$view],
            'messages' => $statement->fetchAll(PDO::FETCH_ASSOC),
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function show(): void
    {
        $user = $this->requireWorkspaceUser();
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $pdo = app_db();
        $statement = $pdo->prepare(
            'SELECT m.id, m.subject, m.body, m.is_priority, m.read_at, m.created_at,
                    s.first_name AS sender_first_name, s.last_name AS sender_last_name, s.email AS sender_email
             FROM messages m JOIN users s ON s.id = m.sender_user_id
             WHERE m.id = :id AND m.recipient_user_id = :user_id'
        );
        $statement->execute(['id' => $id === false ? 0 : (int) $id, 'user_id' => $user['id']]);
        $message = $statement->fetch(PDO::FETCH_ASSOC);
        if ($message === false) {
            set_flash_message('error', 'That message could not be found.');
            redirect('/messenger');
        }

        if ($message['read_at'] === null) {
            $pdo->prepare('UPDATE messages SET read_at = :read_at WHERE id = :id AND recipient_user_id = :user_id')
                ->execute(['read_at' => gmdate('Y-m-d H:i:s'), 'id' => $message['id'], 'user_id' => $user['id']]);
        }

        render('messenger/show', [
            'title' => $message['subject'],
            'user' => $user,
            'message' => $message,
        ]);
    }

    public function compose(): void
    {
        $user = $this->requireWorkspaceUser();
        $toInput = $_GET['to'] ?? '';
        $subjectInput = $_GET['subject'] ?? '';

        render('messenger/compose', [
            'title' => 'Compose message',
            'user' => $user,
            'old' => [
                'to' => is_string($toInput) ? mb_substr($toInput, 0, 255) : '',
                'subject' => is_string($subjectInput) ? mb_substr($subjectInput, 0, 200) : '',
                'body' => '',
                'priority' => false,
            ],
            'error' => null,
        ]);
    }

    public function send(): void
    {
        $user = $this->requireWorkspaceUser();
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            http_response_code(419);
            echo 'Request expired. Refresh the page and try again.';
            return;
        }

        $to = strtolower(trim((string) ($_POST['to'] ?? '')));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $priority = ($_POST['priority'] ?? '') === '1';
        $old = ['to' => $to, 'subject' => $subject, 'body' => $body, 'priority' => $priority];

        $error = null;
        $recipientId = null;
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $error = 'Enter the email address of a Garwin AI member.';
        } elseif ($subject === '' || mb_strlen($subject) > 200) {
            $error = 'Add a subject of up to 200 characters.';
        } elseif ($body === '' || mb_strlen($body) > 10000) {
            $error = 'Write a message of up to 10,000 characters.';
        } else {
            $lookup = app_db()->prepare(
                "SELECT u.id FROM users u LEFT JOIN admin_users a ON a.user_id = u.id
                 WHERE LOWER(u.email) = :email AND (a.role_key IS NULL OR a.role_key <> 'super_admin') LIMIT 1"
            );
            $lookup->execute(['email' => $to]);
            $recipientId = $lookup->fetchColumn();
            if ($recipientId === false) {
                $error = 'No Garwin AI member uses that email address.';
            }
        }

        if ($error !== null) {
            render('messenger/compose', [
                'title' => 'Compose message',
                'user' => $user,
                'old' => $old,
                'error' => $error,
            ], 422);
            return;
        }

        app_db()->prepare(
            'INSERT INTO messages (sender_user_id, recipient_user_id, subject, body, is_priority, created_at)
             VALUES (:sender_user_id, :recipient_user_id, :subject, :body, :is_priority, :created_at)'
        )->execute([
            'sender_user_id' => $user['id'],
            'recipient_user_id' => (int) $recipientId,
            'subject' => $subject,
            'body' => $body,
            'is_priority' => $priority ? 1 : 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        set_flash_message('success', 'Message sent to ' . $to . '.');
        redirect('/messenger');
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
}
