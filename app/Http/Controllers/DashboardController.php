<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use PDO;
use App\Support\Csrf;

final class DashboardController
{
    private const WIDGETS = ['overview', 'weather', 'clock', 'files', 'messages', 'notifications', 'support'];

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
            'SELECT m.plan_key, s.quota_bytes, s.used_bytes, b.budget_cents, b.spent_cents
             FROM memberships m
             JOIN storage_accounts s ON s.user_id = m.user_id
             JOIN ai_budgets b ON b.user_id = m.user_id
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
        $widgetLayout = $this->normalizeLayout(is_array($layout) ? ($layout['widgets'] ?? []) : []);
        $preferencesStatement = app_db()->prepare('SELECT preferences_json FROM user_preferences WHERE user_id = :user_id');
        $preferencesStatement->execute(['user_id' => $user['id']]);
        $preferences = json_decode((string) $preferencesStatement->fetchColumn(), true);
        if (!is_array($preferences)) {
            $preferences = [];
        }
        $folderStatement = app_db()->prepare('SELECT COUNT(*) FROM storage_folders WHERE user_id = :user_id');
        $folderStatement->execute(['user_id' => $user['id']]);

        render('dashboard/index', [
            'title' => 'Dashboard',
            'user' => $user,
            'account' => $account,
            'widgetLayout' => $widgetLayout,
            'preferences' => $preferences,
            'folderCount' => (int) $folderStatement->fetchColumn(),
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
        if (!is_array($layout) || !isset($layout['widgets']) || !is_array($layout['widgets'])
            || !array_is_list($layout['widgets']) || count($layout['widgets']) !== count(self::WIDGETS)) {
            http_response_code(422);
            echo 'Invalid dashboard layout.';
            return;
        }

        $seen = [];
        foreach ($layout['widgets'] as $widget) {
            if (!is_array($widget) || !isset($widget['id'], $widget['size'])
                || !is_string($widget['id']) || !in_array($widget['id'], self::WIDGETS, true)
                || !is_string($widget['size']) || !in_array($widget['size'], ['small', 'wide', 'full'], true)
                || isset($seen[$widget['id']])) {
                http_response_code(422);
                echo 'Invalid dashboard layout.';
                return;
            }
            $seen[$widget['id']] = true;
        }
        if (count($seen) !== count(self::WIDGETS)) {
            http_response_code(422);
            echo 'Invalid dashboard layout.';
            return;
        }

        $normalized = $this->normalizeLayout($layout['widgets']);
        if (count($normalized) !== count($layout['widgets'])) {
            http_response_code(422);
            echo 'Invalid dashboard layout.';
            return;
        }
        $statement = app_db()->prepare(
            'UPDATE dashboard_layouts SET layout_json = :layout_json, updated_at = :updated_at WHERE user_id = :user_id'
        );
        $statement->execute([
            'layout_json' => json_encode(['widgets' => $normalized], JSON_THROW_ON_ERROR),
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

    /** @param mixed $widgets
     *  @return list<array{id: string, size: string}>
     */
    private function normalizeLayout(mixed $widgets): array
    {
        if (!is_array($widgets)) {
            return [];
        }

        $normalized = [];
        foreach ($widgets as $widget) {
            if (!is_array($widget) || !isset($widget['id'], $widget['size'])
                || !is_string($widget['id']) || !in_array($widget['id'], self::WIDGETS, true)
                || !is_string($widget['size']) || !in_array($widget['size'], ['small', 'wide', 'full'], true)
                || in_array($widget['id'], array_column($normalized, 'id'), true)) {
                continue;
            }
            $normalized[] = ['id' => $widget['id'], 'size' => $widget['size']];
        }

        foreach (self::WIDGETS as $id) {
            if (!in_array($id, array_column($normalized, 'id'), true)) {
                $normalized[] = ['id' => $id, 'size' => 'small'];
            }
        }

        return $normalized;
    }
}
