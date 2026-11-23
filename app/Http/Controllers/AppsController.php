<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NavigationService;
use App\Support\Csrf;
use PDO;
use PDOException;

/** The Apps menu (turn apps on or off, open their settings) and workspace search. */
final class AppsController
{
    public function toggle(): void
    {
        $user = $this->requireUser();
        $this->requireToken();
        $key = $_POST['key'] ?? '';
        $navigation = new NavigationService(app_db());
        if (!is_string($key) || !$navigation->toggleActive((int) $user['id'], $key)) {
            set_flash_message('error', 'That app could not be changed.');
        } else {
            $app = $navigation->findApp((int) $user['id'], $key);
            set_flash_message('success', $app['label'] . ($app['active'] ? ' is on.' : ' is off. Turn it back on from Apps.'));
        }

        redirect($this->safeReturn($_POST['return'] ?? null));
    }

    public function saveMenuLink(): void
    {
        $user = $this->requireUser();
        $this->requireToken();
        $id = $_POST['link_id'] ?? null;
        $fields = [];
        foreach (['label', 'url', 'icon', 'color'] as $field) {
            $fields[$field] = is_string($_POST[$field] ?? null) ? $_POST[$field] : '';
        }
        $error = (new NavigationService(app_db()))->saveMenuLink(
            (int) $user['id'],
            is_string($id) && $id !== '' ? $id : null,
            $fields['label'],
            $fields['url'],
            $fields['icon'],
            $fields['color']
        );
        set_flash_message($error === null ? 'success' : 'error', $error ?? (is_string($id) && $id !== '' ? 'Menu link updated.' : 'Link added to your menu.'));
        redirect($this->safeReturn($_POST['return'] ?? null));
    }

    public function moveMenuLink(): void
    {
        $user = $this->requireUser();
        $this->requireToken();
        $id = $_POST['link_id'] ?? '';
        if (is_string($id)) {
            (new NavigationService(app_db()))->moveMenuLink((int) $user['id'], $id, ($_POST['direction'] ?? '') === 'up' ? -1 : 1);
        }
        redirect($this->safeReturn($_POST['return'] ?? null));
    }

    public function deleteMenuLink(): void
    {
        $user = $this->requireUser();
        $this->requireToken();
        $id = $_POST['link_id'] ?? '';
        $deleted = is_string($id) && (new NavigationService(app_db()))->deleteMenuLink((int) $user['id'], $id);
        set_flash_message($deleted ? 'success' : 'error', $deleted ? 'Menu link removed.' : 'That link could not be removed.');
        redirect($this->safeReturn($_POST['return'] ?? null));
    }

    public function settings(): void
    {
        $user = $this->requireUser();
        $key = $_GET['app'] ?? '';
        $app = is_string($key) ? (new NavigationService(app_db()))->findApp((int) $user['id'], $key) : null;
        if ($app === null) {
            set_flash_message('error', 'That app could not be found.');
            redirect('/dashboard');
        }

        render('apps/settings', [
            'title' => $app['label'] . ' settings',
            'user' => $user,
            'app' => $app,
            'flashMessage' => take_flash_message(),
            'pinCount' => count((new NavigationService(app_db()))->pinnedKeys((int) $user['id'])),
        ]);
    }

    public function search(): void
    {
        $user = $this->requireUser();
        $query = trim((string) (is_string($_GET['q'] ?? null) ? $_GET['q'] : ''));
        $query = mb_substr($query, 0, 100);
        $results = [];
        if ($query !== '') {
            $userId = (int) $user['id'];
            $needle = mb_strtolower($query);
            $matches = static fn (string ...$fields): bool => array_reduce(
                $fields,
                static fn (bool $found, string $field): bool => $found || str_contains(mb_strtolower($field), $needle),
                false
            );

            foreach ((new NavigationService(app_db()))->myApps($userId) as $app) {
                if ($matches($app['label'], $app['description'])) {
                    $results['Apps'][] = ['title' => $app['label'], 'detail' => $app['description'], 'href' => $app['href'], 'icon' => $app['icon']];
                }
            }

            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query) . '%';
            $results['Pages'] = $this->rows(
                "SELECT id, title, page_type FROM workspace_pages WHERE user_id = :user_id AND (title LIKE :q ESCAPE '\\' OR content LIKE :q2 ESCAPE '\\') ORDER BY updated_at DESC LIMIT 20",
                $userId,
                $like,
                static fn (array $row): array => ['title' => $row['title'], 'detail' => ['doc' => 'Wordbase doc', 'sheet' => 'Spreadsheet', 'calendar' => 'Calendar'][$row['page_type']] ?? 'Page', 'href' => '/pages/view?id=' . $row['id'], 'icon' => ['doc' => 'wordbase', 'sheet' => 'spreadsheets', 'calendar' => 'calendar'][$row['page_type']] ?? 'app']
            );
            $results['Documents'] = $this->rows(
                "SELECT id, title FROM wordbase_documents WHERE user_id = :user_id AND (title LIKE :q ESCAPE '\\' OR content LIKE :q2 ESCAPE '\\') ORDER BY updated_at DESC LIMIT 20",
                $userId,
                $like,
                static fn (array $row): array => ['title' => $row['title'], 'detail' => 'Wordbase', 'href' => '/wordbase?document=' . $row['id'], 'icon' => 'wordbase']
            );
            $results['Folders'] = $this->rows(
                "SELECT folder_name AS name FROM storage_folders WHERE user_id = :user_id AND folder_name LIKE :q ESCAPE '\\' ORDER BY folder_name LIMIT 20",
                $userId,
                $like,
                static fn (array $row): array => ['title' => $row['name'], 'detail' => 'Storage folder', 'href' => '/storage', 'icon' => 'storage']
            );
            $results['Calendar'] = $this->rows(
                "SELECT title, starts_at FROM calendar_events WHERE user_id = :user_id AND (title LIKE :q ESCAPE '\\' OR description LIKE :q2 ESCAPE '\\') ORDER BY starts_at DESC LIMIT 20",
                $userId,
                $like,
                static fn (array $row): array => ['title' => $row['title'], 'detail' => date('M j, Y g:i a', strtotime((string) $row['starts_at'])), 'href' => '/calendar', 'icon' => 'calendar']
            );
            $results['Messages'] = $this->rows(
                "SELECT id, subject FROM messages WHERE recipient_user_id = :user_id AND (subject LIKE :q ESCAPE '\\' OR body LIKE :q2 ESCAPE '\\') ORDER BY created_at DESC LIMIT 20",
                $userId,
                $like,
                static fn (array $row): array => ['title' => $row['subject'], 'detail' => 'Message', 'href' => '/messenger/message?id=' . $row['id'], 'icon' => 'messenger']
            );
            $results = array_filter($results);
        }

        render('search/index', ['title' => 'Search', 'user' => $user, 'query' => $query, 'results' => $results]);
    }

    /** Runs one search query; a table that is not set up yet simply has no results. */
    private function rows(string $sql, int $userId, string $like, callable $map): array
    {
        try {
            $statement = app_db()->prepare($sql);
            $params = ['user_id' => $userId, 'q' => $like];
            if (str_contains($sql, ':q2')) {
                $params['q2'] = $like;
            }
            $statement->execute($params);

            return array_map($map, $statement->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException) {
            return [];
        }
    }

    private function safeReturn(mixed $return): string
    {
        return is_string($return) && str_starts_with($return, '/') && !str_starts_with($return, '//')
            && !preg_match('/[\r\n\\\\]/', $return) ? $return : '/dashboard';
    }

    private function requireUser(): array
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

    private function requireToken(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            http_response_code(419);
            render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);
            exit;
        }
    }
}
