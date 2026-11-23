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
    public const DEFAULT_PINS = ['wordbase', 'spreadsheets', 'codebase', 'storage', 'appzone'];

    /** The top bar holds at most this many pinned apps. */
    public const MAX_PINS = 5;

    /** Always on the top bar's left side, so they are not pinnable. */
    public const FIXED_TOP_BAR = ['assistant', 'calendar', 'messenger'];

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

    public const MAX_MENU_LINKS = 20;
    public const MENU_LINK_ICONS = ['link', 'globe', 'home', 'star', 'heart', 'folder', 'note', 'users', 'calendar', 'mail-new', 'help', 'app'];

    /** Apps that appear in the Apps menu, with what each one does. Developer only when installed. */
    private const APP_DESCRIPTIONS = [
        'assistant' => 'Chat with the AI Assistant.',
        'wordbase' => 'Write docs, notes and plans.',
        'spreadsheets' => 'Tables, budgets and formulas.',
        'codebase' => 'Write and organize code.',
        'messenger' => 'Messages with other users.',
        'calendar' => 'Events and your schedule.',
        'storage' => 'Your private files and folders.',
        'developer' => 'Build apps, widgets and themes.',
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

        $inactive = $this->inactiveKeys($userId);
        $pins = array_values(array_filter(
            $this->pinnedKeys($userId),
            static fn (string $key): bool => isset($items[$key]) && !in_array($key, $inactive, true)
        ));

        return [
            'items' => $items,
            'pins' => $pins,
            'inactive' => $inactive,
            'otherApps' => array_values(array_filter(
                $installedApps,
                static fn (array $app): bool => $app['launch_url'] !== '/developer'
                    && !in_array('appzone-' . $app['id'], $inactive, true)
            )),
            'pages' => (new WorkspacePageService($this->pdo))->listForUser($userId),
            'menuLinks' => $this->menuLinks($userId),
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

        $pins = array_values(array_filter(
            $pins,
            static fn ($key): bool => is_string($key) && isset(self::ITEMS[$key]) && !in_array($key, self::FIXED_TOP_BAR, true)
        ));

        return array_slice($pins, 0, self::MAX_PINS);
    }

    public function togglePin(int $userId, string $key): bool
    {
        if (!isset(self::ITEMS[$key]) || in_array($key, self::FIXED_TOP_BAR, true)) {
            return false;
        }
        if ($key === 'developer' && !self::developerIsInstalled($this->pdo, $userId)) {
            return false;
        }

        $preferences = $this->preferences($userId);
        $pins = $this->pinnedKeys($userId);
        if (in_array($key, $pins, true)) {
            $pins = array_values(array_diff($pins, [$key]));
        } elseif (count($pins) >= self::MAX_PINS) {
            return false;
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

    /** @return list<string> */
    public function inactiveKeys(int $userId): array
    {
        $inactive = $this->preferences($userId)['inactive_apps'] ?? [];

        return is_array($inactive) ? array_values(array_filter($inactive, 'is_string')) : [];
    }

    /**
     * Every app the user has: the base apps plus anything installed from AppZone.
     *
     * @return list<array{key: string, label: string, icon: string, href: string, description: string, active: bool, pinned: bool, pinnable: bool}>
     */
    public function myApps(int $userId): array
    {
        $inactive = $this->inactiveKeys($userId);
        $pins = $this->pinnedKeys($userId);
        $installed = $this->installedApps($userId);
        $apps = [];
        foreach (self::APP_DESCRIPTIONS as $key => $description) {
            if ($key === 'developer' && !in_array('/developer', array_column($installed, 'launch_url'), true)) {
                continue;
            }
            $item = self::ITEMS[$key];
            $apps[] = [
                'key' => $key,
                'label' => $item['label'],
                'icon' => $item['icon'],
                'href' => $item['href'],
                'description' => $description,
                'active' => !in_array($key, $inactive, true),
                'pinned' => in_array($key, $pins, true),
                'pinnable' => !in_array($key, self::FIXED_TOP_BAR, true),
            ];
        }
        foreach ($installed as $app) {
            if ($app['launch_url'] === '/developer') {
                continue;
            }
            $key = 'appzone-' . $app['id'];
            $apps[] = [
                'key' => $key,
                'label' => (string) $app['name'],
                'icon' => 'app',
                'href' => (string) $app['launch_url'],
                'description' => (string) $app['description'],
                'active' => !in_array($key, $inactive, true),
                'pinned' => false,
                'pinnable' => false,
            ];
        }

        return $apps;
    }

    public function findApp(int $userId, string $key): ?array
    {
        foreach ($this->myApps($userId) as $app) {
            if ($app['key'] === $key) {
                return $app;
            }
        }

        return null;
    }

    /** Turns an app on or off. Off apps leave the sidebar and the top bar until turned back on. */
    public function toggleActive(int $userId, string $key): bool
    {
        if ($this->findApp($userId, $key) === null) {
            return false;
        }
        $preferences = $this->preferences($userId);
        $inactive = $this->inactiveKeys($userId);
        $inactive = in_array($key, $inactive, true)
            ? array_values(array_diff($inactive, [$key]))
            : [...$inactive, $key];
        $preferences['inactive_apps'] = $inactive;
        $this->savePreferences($userId, $preferences);

        return true;
    }

    /** @return list<array{id: string, label: string, url: string, icon: string, color: string}> */
    public function menuLinks(int $userId): array
    {
        $links = $this->preferences($userId)['menu_links'] ?? [];
        if (!is_array($links)) {
            return [];
        }

        return array_values(array_filter($links, static fn ($link): bool => is_array($link)
            && is_string($link['id'] ?? null) && is_string($link['label'] ?? null) && is_string($link['url'] ?? null)
            && self::validMenuUrl($link['url']) !== null
            && in_array($link['icon'] ?? '', self::MENU_LINK_ICONS, true) && in_array($link['color'] ?? '', ICON_TILE_PALETTE, true)));
    }

    /** Adds or edits a sidebar link. Returns an error message, or null on success. */
    public function saveMenuLink(int $userId, ?string $id, string $label, string $url, string $icon, string $color): ?string
    {
        $label = trim(preg_replace('/\s+/u', ' ', $label) ?? '');
        if ($label === '' || mb_strlen($label) > 40) {
            return 'Give the link a name of up to 40 characters.';
        }
        $url = self::validMenuUrl(trim($url));
        if ($url === null) {
            return 'Use a full web address (https://…) or a page here that starts with /.';
        }
        if (!in_array($icon, self::MENU_LINK_ICONS, true)) {
            $icon = 'link';
        }
        if (!in_array($color, ICON_TILE_PALETTE, true)) {
            $color = 'sky';
        }

        $links = $this->menuLinks($userId);
        if ($id === null) {
            if (count($links) >= self::MAX_MENU_LINKS) {
                return 'The menu holds up to ' . self::MAX_MENU_LINKS . ' links.';
            }
            $links[] = ['id' => bin2hex(random_bytes(4)), 'label' => $label, 'url' => $url, 'icon' => $icon, 'color' => $color];
        } else {
            $index = array_search($id, array_column($links, 'id'), true);
            if ($index === false) {
                return 'That link no longer exists.';
            }
            $links[$index] = ['id' => $id, 'label' => $label, 'url' => $url, 'icon' => $icon, 'color' => $color];
        }
        $this->storeMenuLinks($userId, $links);

        return null;
    }

    public function moveMenuLink(int $userId, string $id, int $direction): bool
    {
        $links = $this->menuLinks($userId);
        $index = array_search($id, array_column($links, 'id'), true);
        $target = $index === false ? null : $index + ($direction < 0 ? -1 : 1);
        if ($target === null || !isset($links[$target])) {
            return false;
        }
        [$links[$index], $links[$target]] = [$links[$target], $links[$index]];
        $this->storeMenuLinks($userId, $links);

        return true;
    }

    public function deleteMenuLink(int $userId, string $id): bool
    {
        $links = $this->menuLinks($userId);
        $remaining = array_values(array_filter($links, static fn (array $link): bool => $link['id'] !== $id));
        if (count($remaining) === count($links)) {
            return false;
        }
        $this->storeMenuLinks($userId, $remaining);

        return true;
    }

    /** An http(s) address, or a path on this site. Anything else (javascript:, //host) is refused. */
    public static function validMenuUrl(string $url): ?string
    {
        if ($url === '' || mb_strlen($url) > 500 || preg_match('/[\s<>"\x00-\x1f]/', $url)) {
            return null;
        }
        if (str_starts_with($url, '/') && !str_starts_with($url, '//') && !str_contains($url, '\\')) {
            return $url;
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            $url = 'https://' . $url;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true) && is_string($host) && $host !== '' ? $url : null;
    }

    private function storeMenuLinks(int $userId, array $links): void
    {
        $preferences = $this->preferences($userId);
        $preferences['menu_links'] = array_values($links);
        $this->savePreferences($userId, $preferences);
    }

    private function savePreferences(int $userId, array $preferences): void
    {
        $update = $this->pdo->prepare(
            'UPDATE user_preferences SET preferences_json = :preferences_json, updated_at = :updated_at WHERE user_id = :user_id'
        );
        $update->execute([
            'preferences_json' => json_encode($preferences, JSON_THROW_ON_ERROR),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
    }

    private function preferences(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT preferences_json FROM user_preferences WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $preferences = json_decode((string) $statement->fetchColumn(), true);

        return is_array($preferences) ? $preferences : [];
    }

    /** @return list<array{id: int|string, name: string, description: string, launch_url: string}> */
    private function installedApps(int $userId): array
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT i.id, i.name, i.description, i.launch_url
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
