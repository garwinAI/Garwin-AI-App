<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\NavigationService;
use App\Support\Csrf;

/** Pins and unpins workspace apps on the top bar, and serves base apps that are not built yet. */
final class NavigationController
{
    public function togglePin(): void
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

        $key = $_POST['key'] ?? '';
        if (!is_string($key) || !(new NavigationService(app_db()))->togglePin((int) $user['id'], $key)) {
            set_flash_message('error', 'The top bar holds up to ' . NavigationService::MAX_PINS . ' apps. Unpin one first.');
            redirect('/dashboard');
        }

        $return = $_POST['return'] ?? '/dashboard';
        $return = is_string($return) && str_starts_with($return, '/') && !str_starts_with($return, '//')
            && !preg_match('/[\r\n\\\\]/', $return) ? $return : '/dashboard';
        redirect($return);
    }

    public function profile(): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }

        $statement = app_db()->prepare(
            'SELECT u.created_at, COALESCE(p.display_name, m.plan_key) AS plan_name, s.quota_bytes, s.used_bytes
             FROM users u
             LEFT JOIN memberships m ON m.user_id = u.id
             LEFT JOIN membership_plans p ON p.plan_key = m.plan_key
             LEFT JOIN storage_accounts s ON s.user_id = u.id
             WHERE u.id = :id LIMIT 1'
        );
        $statement->execute(['id' => $user['id']]);
        $account = $statement->fetch(\PDO::FETCH_ASSOC) ?: [];

        render('profile/index', ['title' => 'Your profile', 'user' => $user, 'account' => $account]);
    }

    public function help(): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }

        render('help/index', ['title' => 'Help', 'user' => $user]);
    }

    public function spreadsheets(): void
    {
        $this->notBuiltYet('Spreadsheets', 'Build tables, budgets, and models with formulas.');
    }

    public function codebase(): void
    {
        $this->notBuiltYet('Codebase', 'Write, organize, and review code projects.');
    }

    private function notBuiltYet(string $name, string $description): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }

        render('apps/not-built', [
            'title' => $name,
            'user' => $user,
            'baseAppName' => $name,
            'appDescription' => $description,
        ]);
    }
}
