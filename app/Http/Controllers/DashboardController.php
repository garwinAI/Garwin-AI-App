<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use PDO;
use App\Support\Csrf;

final class DashboardController
{
    /** Every widget that can be placed on the dashboard: id => [default title, icon, what it shows]. */
    public const WIDGETS = [
        'welcome' => ['Welcome', 'welcome', 'Greeting, today’s date, your plan and the + menu.'],
        'overview' => ['Overview', 'overview', 'Your plan, storage and user ID.'],
        'notify' => ['Notify', 'bell', 'Unread messages, notifications and support replies.'],
        'clock' => ['Clock', 'clock', 'The time and date where you are.'],
        'files' => ['Files', 'drive', 'Folders and storage used.'],
        'weather' => ['Weather', 'weather', 'Weather for your location, found automatically.'],
        'messages' => ['Messages', 'messenger', 'Your newest unread messages.'],
        'notifications' => ['Notifications', 'bell', 'Updates about your workspace.'],
        'support' => ['Support', 'help', 'Get help from the Garwin AI team.'],
    ];
    private const DEFAULT_LAYOUT = ['welcome', 'overview', 'notify', 'clock', 'files', 'weather'];
    private const DEFAULT_SIZES = ['welcome' => 'full', 'overview' => 'wide', 'messages' => 'wide'];
    private const LAYOUT_VERSION = 3;
    public const WINDOW_KINDS = ['note' => 'Note', 'page' => 'Page'];
    public const MAX_WINDOWS = 12;
    public const MAX_NOTE = 5000;
    public const MAX_TITLE = 40;

    public function index(): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }

        $statement = app_db()->prepare(
            'SELECT m.plan_key, COALESCE(p.display_name, m.plan_key) AS plan_name, s.quota_bytes, s.used_bytes
             FROM memberships m
             JOIN storage_accounts s ON s.user_id = m.user_id
             LEFT JOIN membership_plans p ON p.plan_key = m.plan_key
             WHERE m.user_id = :user_id LIMIT 1'
        );
        $statement->execute(['user_id' => $user['id']]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);
        if ($account === false) {
            throw new \RuntimeException('Account setup records are incomplete.');
        }

        $layoutStatement = app_db()->prepare('SELECT layout_json FROM dashboard_layouts WHERE user_id = :user_id');
        $layoutStatement->execute(['user_id' => $user['id']]);
        $layout = json_decode((string) $layoutStatement->fetchColumn(), true);
        $widgetLayout = $this->layoutFromSaved(is_array($layout) ? $layout : []);
        $preferencesStatement = app_db()->prepare('SELECT preferences_json FROM user_preferences WHERE user_id = :user_id');
        $preferencesStatement->execute(['user_id' => $user['id']]);
        $preferences = json_decode((string) $preferencesStatement->fetchColumn(), true);
        if (!is_array($preferences)) {
            $preferences = [];
        }
        $folderStatement = app_db()->prepare('SELECT COUNT(*) FROM storage_folders WHERE user_id = :user_id');
        $folderStatement->execute(['user_id' => $user['id']]);
        $unreadMessages = [];
        $unreadCount = 0;
        try {
            $countStatement = app_db()->prepare('SELECT COUNT(*) FROM messages WHERE recipient_user_id = :user_id AND read_at IS NULL');
            $countStatement->execute(['user_id' => $user['id']]);
            $unreadCount = (int) $countStatement->fetchColumn();
            $messageStatement = app_db()->prepare(
                'SELECT m.id, m.subject, m.is_priority, s.first_name, s.last_name
                 FROM messages m JOIN users s ON s.id = m.sender_user_id
                 WHERE m.recipient_user_id = :user_id AND m.read_at IS NULL
                 ORDER BY m.created_at DESC, m.id DESC LIMIT 3'
            );
            $messageStatement->execute(['user_id' => $user['id']]);
            $unreadMessages = $messageStatement->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            $unreadMessages = [];
        }

        render('dashboard/index', [
            'title' => 'Dashboard',
            'user' => $user,
            'account' => $account,
            'widgetLayout' => $widgetLayout,
            'preferences' => $preferences,
            'folderCount' => (int) $folderStatement->fetchColumn(),
            'unreadMessages' => $unreadMessages,
            'unreadCount' => $unreadCount,
            // Notifications and support replies have no inbox yet, so they are always caught up.
            'notifyCounts' => ['messages' => $unreadCount, 'notifications' => 0, 'support' => 0],
            'widgetCatalog' => self::WIDGETS,
            'windowPages' => $this->windowPages((int) $user['id'], $widgetLayout),
        ]);
    }

    public function saveLayout(): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            http_response_code(419);
            echo 'Request expired. Refresh the page and try again.';
            return;
        }

        $layoutInput = $_POST['layout_json'] ?? '';
        $layout = is_string($layoutInput) ? json_decode($layoutInput, true) : null;
        $normalized = is_array($layout) && isset($layout['widgets']) && is_array($layout['widgets']) && array_is_list($layout['widgets'])
            ? $this->normalizeLayout($layout['widgets'])
            : null;
        if ($normalized === null || count($normalized) !== count($layout['widgets'])) {
            http_response_code(422);
            echo 'Invalid dashboard layout.';
            return;
        }
        $statement = app_db()->prepare(
            'UPDATE dashboard_layouts SET layout_json = :layout_json, updated_at = :updated_at WHERE user_id = :user_id'
        );
        $statement->execute([
            'layout_json' => json_encode(['version' => self::LAYOUT_VERSION, 'widgets' => $normalized], JSON_THROW_ON_ERROR),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $user['id'],
        ]);
        if ($statement->rowCount() === 0) {
            $check = app_db()->prepare('SELECT 1 FROM dashboard_layouts WHERE user_id = :user_id');
            $check->execute(['user_id' => $user['id']]);
            if ($check->fetchColumn() === false) {
                throw new \RuntimeException('Dashboard layout record is missing.');
            }
        }

        http_response_code(204);
    }

    /**
     * The layout to show. Version 1 layouts always held every widget; they gain Notify
     * where Weather was, and Weather moves to the end.
     *
     * @return list<array{id: string, size: string, title: string}>
     */
    private function layoutFromSaved(array $saved): array
    {
        $widgets = $this->normalizeLayout($saved['widgets'] ?? []);
        $version = (int) ($saved['version'] ?? 1);
        if ($version >= self::LAYOUT_VERSION) {
            return $widgets;
        }
        if ($version < 2 && $widgets === []) {
            return array_map(
                static fn (string $id): array => ['id' => $id, 'size' => self::DEFAULT_SIZES[$id] ?? 'small', 'title' => ''],
                self::DEFAULT_LAYOUT
            );
        }

        $ids = array_column($widgets, 'id');
        if ($version < 2 && !in_array('notify', $ids, true)) {
            // Version 1 layouts held every widget: Notify takes Weather's place and Weather moves to the end.
            $weatherIndex = array_search('weather', $ids, true);
            $notify = ['id' => 'notify', 'size' => 'small', 'title' => ''];
            if ($weatherIndex === false) {
                array_splice($widgets, 1, 0, [$notify]);
            } else {
                $weather = $widgets[$weatherIndex];
                $widgets[$weatherIndex] = $notify;
                $widgets[] = $weather;
            }
        }
        if (!in_array('welcome', array_column($widgets, 'id'), true)) {
            // Version 3 turned the welcome banner into a widget; it starts at the top.
            array_unshift($widgets, ['id' => 'welcome', 'size' => 'full', 'title' => '']);
        }

        return $widgets;
    }

    /** Adds a Note window, or a window showing one of the user's pages, to the end of the dashboard. */
    public function addWindow(): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            http_response_code(419);
            render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);
            return;
        }

        $kind = $_POST['kind'] ?? '';
        $window = null;
        if ($kind === 'note') {
            $window = ['id' => 'window-' . bin2hex(random_bytes(4)), 'size' => 'small', 'title' => 'Note', 'kind' => 'note', 'note' => ''];
        } elseif ($kind === 'page') {
            $pageId = filter_var($_POST['page_id'] ?? null, FILTER_VALIDATE_INT);
            $page = is_int($pageId) ? (new \App\Services\WorkspacePageService(app_db()))->find((int) $user['id'], $pageId) : null;
            if ($page !== null) {
                $window = ['id' => 'window-' . bin2hex(random_bytes(4)), 'size' => 'wide', 'title' => mb_substr((string) $page['title'], 0, self::MAX_TITLE), 'kind' => 'page', 'page_id' => $page['id']];
            }
        }
        $layout = $this->storedLayout((int) $user['id']);
        $windowCount = count(array_filter($layout, static fn (array $widget): bool => isset($widget['kind'])));
        if ($window === null || $windowCount >= self::MAX_WINDOWS) {
            set_flash_message('error', $window === null ? 'Choose a window to add.' : 'A dashboard can hold up to ' . self::MAX_WINDOWS . ' windows.');
            redirect('/dashboard');
        }
        $layout[] = $window;
        $this->storeLayout((int) $user['id'], $layout);
        set_flash_message('success', 'Window added to the end of your dashboard. Drag it anywhere.');
        redirect('/dashboard#' . $window['id']);
    }

    /** @return list<array<string, mixed>> */
    private function storedLayout(int $userId): array
    {
        $statement = app_db()->prepare('SELECT layout_json FROM dashboard_layouts WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $layout = json_decode((string) $statement->fetchColumn(), true);

        return $this->layoutFromSaved(is_array($layout) ? $layout : []);
    }

    private function storeLayout(int $userId, array $widgets): void
    {
        $statement = app_db()->prepare(
            'UPDATE dashboard_layouts SET layout_json = :layout_json, updated_at = :updated_at WHERE user_id = :user_id'
        );
        $statement->execute([
            'layout_json' => json_encode(['version' => self::LAYOUT_VERSION, 'widgets' => $this->normalizeLayout($widgets)], JSON_THROW_ON_ERROR),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
    }

    /** @param mixed $widgets
     *  @return list<array{id: string, size: string, title: string}>
     */
    private function normalizeLayout(mixed $widgets): array
    {
        if (!is_array($widgets)) {
            return [];
        }

        $normalized = [];
        foreach ($widgets as $widget) {
            if (!is_array($widget) || !isset($widget['id'], $widget['size'])
                || !is_string($widget['id']) || !is_string($widget['size']) || !in_array($widget['size'], ['small', 'wide', 'full'], true)
                || in_array($widget['id'], array_column($normalized, 'id'), true)) {
                continue;
            }
            $isWindow = (bool) preg_match('/^window-[a-f0-9]{8}$/', $widget['id']);
            if (!$isWindow && !isset(self::WIDGETS[$widget['id']])) {
                continue;
            }
            $title = $widget['title'] ?? '';
            if (!is_string($title)) {
                continue;
            }
            $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
            if (mb_strlen($title) > self::MAX_TITLE) {
                continue;
            }
            if ($isWindow) {
                $kind = $widget['kind'] ?? '';
                $entry = ['id' => $widget['id'], 'size' => $widget['size'], 'title' => $title === '' ? 'Window' : $title, 'kind' => $kind];
                if ($kind === 'note') {
                    $note = $widget['note'] ?? '';
                    if (!is_string($note) || mb_strlen($note) > self::MAX_NOTE) {
                        continue;
                    }
                    $entry['note'] = $note;
                } elseif ($kind === 'page') {
                    $pageId = $widget['page_id'] ?? null;
                    if (!is_int($pageId) && !(is_string($pageId) && ctype_digit($pageId))) {
                        continue;
                    }
                    $entry['page_id'] = (int) $pageId;
                } else {
                    continue;
                }
                if (count(array_filter($normalized, static fn (array $item): bool => isset($item['kind']))) >= self::MAX_WINDOWS) {
                    continue;
                }
                $normalized[] = $entry;
                continue;
            }
            // A title that matches the default is stored as "use the default".
            if ($title === self::WIDGETS[$widget['id']][0]) {
                $title = '';
            }
            $normalized[] = ['id' => $widget['id'], 'size' => $widget['size'], 'title' => $title];
        }

        return $normalized;
    }

    /** Pages shown in Page windows, keyed by page id. A deleted page is simply missing. */
    private function windowPages(int $userId, array $layout): array
    {
        $service = new \App\Services\WorkspacePageService(app_db());
        $pages = [];
        foreach ($layout as $widget) {
            if (($widget['kind'] ?? '') === 'page' && !isset($pages[$widget['page_id']])) {
                $page = $service->find($userId, (int) $widget['page_id']);
                if ($page !== null) {
                    $pages[$widget['page_id']] = $page;
                }
            }
        }

        return $pages;
    }
}
