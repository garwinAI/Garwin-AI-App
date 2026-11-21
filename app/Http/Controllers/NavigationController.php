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
            http_response_code(422);
            echo 'That item cannot be pinned.';
            return;
        }

        $return = $_POST['return'] ?? '/dashboard';
        $return = is_string($return) && str_starts_with($return, '/') && !str_starts_with($return, '//')
            && !preg_match('/[\r\n\\\\]/', $return) ? $return : '/dashboard';
        redirect($return);
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
