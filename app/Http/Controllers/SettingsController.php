<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Csrf;
final class SettingsController
{
    public function index(): void
    {
        $user = $this->requireUser();
        $statement = app_db()->prepare('SELECT preferences_json FROM user_preferences WHERE user_id = :user_id');
        $statement->execute(['user_id' => $user['id']]);
        $preferences = json_decode((string) $statement->fetchColumn(), true);

        render('settings/index', [
            'title' => 'Workspace settings',
            'user' => $user,
            'preferences' => is_array($preferences) ? $preferences : [],
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function save(): void
    {
        $user = $this->requireUser();
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            http_response_code(419);
            render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);
            return;
        }

        $locationInput = $_POST['weather_location'] ?? '';
        $timeFormatInput = $_POST['time_format'] ?? '24';
        $storageViewInput = $_POST['storage_view'] ?? 'grid';
        if (!is_string($locationInput) || !is_string($timeFormatInput) || !is_string($storageViewInput)) {
            set_flash_message('error', 'Check your settings and try again.');
            redirect('/settings');
        }
        $location = trim($locationInput);
        $timeFormat = $timeFormatInput;
        $storageView = $storageViewInput;
        if (mb_strlen($location) > 100 || preg_match('/[\x00-\x1F\x7F]/', $location)
            || !in_array($timeFormat, ['12', '24'], true)
            || !in_array($storageView, ['grid', 'list'], true)) {
            set_flash_message('error', 'Check your settings and try again.');
            redirect('/settings');
        }

        $pdo = app_db();
        $statement = $pdo->prepare('SELECT preferences_json FROM user_preferences WHERE user_id = :user_id');
        $statement->execute(['user_id' => $user['id']]);
        $preferences = json_decode((string) $statement->fetchColumn(), true);
        $preferences = is_array($preferences) ? $preferences : [];
        $preferences['weather_location'] = $location;
        $preferences['time_format'] = $timeFormat;
        $preferences['storage_view'] = $storageView;

        $update = $pdo->prepare(
            'UPDATE user_preferences SET preferences_json = :preferences_json, updated_at = :updated_at WHERE user_id = :user_id'
        );
        $update->execute([
            'preferences_json' => json_encode($preferences, JSON_THROW_ON_ERROR),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $user['id'],
        ]);
        if ($update->rowCount() === 0) {
            $check = $pdo->prepare('SELECT 1 FROM user_preferences WHERE user_id = :user_id');
            $check->execute(['user_id' => $user['id']]);
            if ($check->fetchColumn() === false) {
                throw new \RuntimeException('Workspace settings record is missing.');
            }
        }

        set_flash_message('success', 'Workspace settings saved.');
        redirect('/settings');
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
}
