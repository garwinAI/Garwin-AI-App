<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * Builds the signed-in workspace navigation: sidebar items, installed AppZone apps,
 * the user's storage drive label, Messenger counts, and the apps pinned to the top bar.
 */
final class NavigationService
{
    public const DEFAULT_PINS = ['wordbase', 'spreadsheets', 'codebase', 'calendar', 'messenger'];

    /** Built-in destinations. Developer is added only when installed from AppZone. */
    private const ITEMS = [
        'dashboard' => ['label' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'dashboard'],
        'assistant' => ['label' => 'AI Assistant', 'href' => '/assistant', 'icon' => 'assistant'],
        'storage' => ['label' => 'Storage', 'href' => '/storage', 'icon' => 'storage'],
        'calendar' => ['label' => 'Calendar', 'href' => '/calendar', 'icon' => 'calendar'],
        'messenger' => ['label' => 'Messenger', 'href' => '/messenger', 'icon' => 'messenger'],
        'wordbase' => ['label' => 'Wordbase', 'href' => '/wordbase', 'icon' => 'wordbase'],
        'spreadsheets' => ['label' => 'Spreadsheets', 'href' => '/spreadsheets', 'icon' => 'spreadsheets'],
        'codebase' => ['label' => 'Codebase', 'href' => '/codebase', 'icon' => 'codebase'],
        'appzone' => ['label' => 'AppZone', 'href' => '/appzone', 'icon' => 'appzone'],
        'developer' => ['label' => 'Developer', 'href' => '/developer', 'icon' => 'developer'],
    ];

    /** AppZone launch targets that are widgets or themes rather than apps with their own page. */
    private const NON_APP_LAUNCH_TARGETS = ['/dashboard', '/settings'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public static function developerIsInstalled(PDO $pdo, int $userId): bool
    {
        $statement = $pdo->prepare(
            "SELECT 1 FROM appzone_installs x JOIN appzone_items i ON i.id = x.app_id
             WHERE x.user_id = :user_id AND i.launch_url = '/developer' LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    public function forUser(array $user): array
    {
        $userId = (int) $user['id'];
        $installedApps = $this->installedApps($userId);
        $items = self::ITEMS;
        $developerInstalled = false;
        foreach ($installedApps as $app) {
            if ($app['launch_url'] === '/developer') {
                $developerInstalled = true;
            }
        }
        if (!$developerInstalled) {
            unset($items['developer']);
        }

        $pins = array_values(array_filter(
            $this->pinnedKeys($userId),
            static fn (string $key): bool => isset($items[$key])
        ));

        return [
            'items' => $items,
            'pins' => $pins,
            'otherApps' => array_values(array_filter(
                $installedApps,
                static fn (array $app): bool => $app['launch_url'] !== '/developer'
            )),
            'driveLabel' => 'USD-' . $user['user_id'] . '-001',
            'messageCounts' => $this->messageCounts($userId),
        ];
    }

    /** @return list<string> */
    public function pinnedKeys(int $userId): array
    {
        $preferences = $this->preferences($userId);
        $pins = $preferences['pinned_apps'] ?? null;
        if (!is_array($pins)) {
            return self::DEFAULT_PINS;
        }

        return array_values(array_filter($pins, static fn ($key): bool => is_string($key) && isset(self::ITEMS[$key])));
    }

    public function togglePin(int $userId, string $key): bool
    {
        if (!isset(self::ITEMS[$key])) {
            return false;
        }
        if ($key === 'developer' && !self::developerIsInstalled($this->pdo, $userId)) {
            return false;
        }

        $preferences = $this->preferences($userId);
        $pins = $this->pinnedKeys($userId);
        if (in_array($key, $pins, true)) {
            $pins = array_values(array_diff($pins, [$key]));
        } else {
            $pins[] = $key;
        }
        $preferences['pinned_apps'] = $pins;

        $update = $this->pdo->prepare(
            'UPDATE user_preferences SET preferences_json = :preferences_json, updated_at = :updated_at WHERE user_id = :user_id'
        );
        $update->execute([
            'preferences_json' => json_encode($preferences, JSON_THROW_ON_ERROR),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);

        return true;
    }

    private function preferences(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT preferences_json FROM user_preferences WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $preferences = json_decode((string) $statement->fetchColumn(), true);

        return is_array($preferences) ? $preferences : [];
    }

    /** @return list<array{name: string, launch_url: string}> */
    private function installedApps(int $userId): array
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT i.name, i.launch_url
                 FROM appzone_installs x JOIN appzone_items i ON i.id = x.app_id
                 WHERE x.user_id = :user_id AND i.is_published = 1
                 ORDER BY x.installed_at, x.id'
            );
            $statement->execute(['user_id' => $userId]);
            $apps = $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException) {
            return [];
        }

        return array_values(array_filter(
            $apps,
            static fn (array $app): bool => !in_array($app['launch_url'], self::NON_APP_LAUNCH_TARGETS, true)
        ));
    }

    /** @return array{new: int, read: int, priority: int} */
    private function messageCounts(int $userId): array
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT
                    SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) AS new_count,
                    SUM(CASE WHEN read_at IS NOT NULL THEN 1 ELSE 0 END) AS read_count,
                    SUM(CASE WHEN is_priority = 1 THEN 1 ELSE 0 END) AS priority_count
                 FROM messages WHERE recipient_user_id = :user_id'
            );
            $statement->execute(['user_id' => $userId]);
            $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException) {
            $row = [];
        }

        return [
            'new' => (int) ($row['new_count'] ?? 0),
            'read' => (int) ($row['read_count'] ?? 0),
            'priority' => (int) ($row['priority_count'] ?? 0),
        ];
    }
}
