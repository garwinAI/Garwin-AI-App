<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

/**
 * Workspace pages: a user's own Wordbase docs, spreadsheets and calendars that live
 * in the Pages menu and the sidebar. Each page belongs to exactly one user.
 */
final class WorkspacePageService
{
    public const TYPES = [
        'doc' => ['label' => 'Wordbase doc', 'icon' => 'wordbase', 'default_title' => 'Untitled doc'],
        'sheet' => ['label' => 'Spreadsheet', 'icon' => 'spreadsheets', 'default_title' => 'Untitled spreadsheet'],
        'calendar' => ['label' => 'Calendar', 'icon' => 'calendar', 'default_title' => 'Untitled calendar'],
    ];

    public const MAX_PAGES = 100;
    public const MAX_TITLE = 120;
    public const MAX_DOC_LENGTH = 200000;
    public const SHEET_COLUMNS = 12;
    public const SHEET_ROWS = 40;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{id: int, page_type: string, title: string, updated_at: string}> */
    public function listForUser(int $userId): array
    {
        try {
            $statement = $this->pdo->prepare(
                'SELECT id, page_type, title, updated_at FROM workspace_pages
                 WHERE user_id = :user_id ORDER BY position ASC, id ASC'
            );
            $statement->execute(['user_id' => $userId]);
        } catch (PDOException) {
            // The pages migration has not been run yet.
            return [];
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'page_type' => (string) $row['page_type'],
            'title' => (string) $row['title'],
            'updated_at' => (string) $row['updated_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function find(int $userId, int $pageId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, page_type, title, content, updated_at FROM workspace_pages WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $statement->execute(['id' => $pageId, 'user_id' => $userId]);
        $page = $statement->fetch(PDO::FETCH_ASSOC);
        if ($page === false) {
            return null;
        }
        $page['id'] = (int) $page['id'];

        return $page;
    }

    public function create(int $userId, string $type): ?int
    {
        if (!isset(self::TYPES[$type]) || count($this->listForUser($userId)) >= self::MAX_PAGES) {
            return null;
        }

        return $this->insert($userId, $type, $this->uniqueTitle($userId, self::TYPES[$type]['default_title']), self::emptyContent($type));
    }

    public function duplicate(int $userId, int $pageId): ?int
    {
        $page = $this->find($userId, $pageId);
        if ($page === null || count($this->listForUser($userId)) >= self::MAX_PAGES) {
            return null;
        }
        $title = mb_substr($page['title'] . ' (copy)', 0, self::MAX_TITLE);

        return $this->insert($userId, (string) $page['page_type'], $this->uniqueTitle($userId, $title), (string) $page['content']);
    }

    public function delete(int $userId, int $pageId): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM workspace_pages WHERE id = :id AND user_id = :user_id');
        $statement->execute(['id' => $pageId, 'user_id' => $userId]);

        return $statement->rowCount() > 0;
    }

    /** Saves a page's title and content. Returns an error message, or null on success. */
    public function save(int $userId, int $pageId, string $title, string $content): ?string
    {
        $page = $this->find($userId, $pageId);
        if ($page === null) {
            return 'That page no longer exists.';
        }
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        if ($title === '') {
            $title = self::TYPES[$page['page_type']]['default_title'] ?? 'Untitled page';
        }
        if (mb_strlen($title) > self::MAX_TITLE) {
            return 'Page names can be up to ' . self::MAX_TITLE . ' characters.';
        }
        $normalized = self::normalizeContent((string) $page['page_type'], $content);
        if ($normalized === null) {
            return 'That page content could not be saved.';
        }

        $statement = $this->pdo->prepare(
            'UPDATE workspace_pages SET title = :title, content = :content, updated_at = :updated_at WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'title' => $title,
            'content' => $normalized,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $pageId,
            'user_id' => $userId,
        ]);

        return null;
    }

    public static function emptyContent(string $type): string
    {
        return match ($type) {
            'sheet' => '{"cells":{}}',
            'calendar' => '{"events":{}}',
            default => '',
        };
    }

    /** Validates and canonicalizes page content for its type; null when invalid. */
    public static function normalizeContent(string $type, string $content): ?string
    {
        if ($type === 'doc') {
            return mb_strlen($content) <= self::MAX_DOC_LENGTH ? $content : null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        if ($type === 'sheet') {
            $cells = $data['cells'] ?? [];
            if (!is_array($cells)) {
                return null;
            }
            $lastColumn = chr(ord('A') + self::SHEET_COLUMNS - 1);
            $clean = [];
            foreach ($cells as $ref => $value) {
                if (!is_string($ref) || !is_string($value) || mb_strlen($value) > 1000
                    || !preg_match('/^([A-' . $lastColumn . '])([1-9]\d?)$/', $ref, $match) || (int) $match[2] > self::SHEET_ROWS) {
                    return null;
                }
                if ($value !== '') {
                    $clean[$ref] = $value;
                }
            }

            return json_encode(['cells' => (object) $clean], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        if ($type === 'calendar') {
            $events = $data['events'] ?? [];
            if (!is_array($events) || count($events) > 1000) {
                return null;
            }
            $clean = [];
            foreach ($events as $day => $items) {
                if (!is_string($day) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || !is_array($items) || count($items) > 20) {
                    return null;
                }
                [$year, $month, $date] = array_map('intval', explode('-', $day));
                if (!checkdate($month, $date, $year)) {
                    return null;
                }
                $list = [];
                foreach ($items as $item) {
                    if (!is_string($item) || mb_strlen($item) > 200) {
                        return null;
                    }
                    $item = trim($item);
                    if ($item !== '') {
                        $list[] = $item;
                    }
                }
                if ($list !== []) {
                    $clean[$day] = $list;
                }
            }
            ksort($clean);

            return json_encode(['events' => (object) $clean], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return null;
    }

    private function insert(int $userId, string $type, string $title, string $content): int
    {
        $positionStatement = $this->pdo->prepare('SELECT COALESCE(MAX(position), 0) FROM workspace_pages WHERE user_id = :user_id');
        $positionStatement->execute(['user_id' => $userId]);
        $now = gmdate('Y-m-d H:i:s');
        $statement = $this->pdo->prepare(
            'INSERT INTO workspace_pages (user_id, page_type, title, content, position, created_at, updated_at)
             VALUES (:user_id, :page_type, :title, :content, :position, :created_at, :updated_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'page_type' => $type,
            'title' => $title,
            'content' => $content,
            'position' => (int) $positionStatement->fetchColumn() + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function uniqueTitle(int $userId, string $base): string
    {
        $titles = array_column($this->listForUser($userId), 'title');
        if (!in_array($base, $titles, true)) {
            return $base;
        }
        for ($number = 2; ; $number++) {
            $candidate = mb_substr($base, 0, self::MAX_TITLE - 4) . ' ' . $number;
            if (!in_array($candidate, $titles, true)) {
                return $candidate;
            }
        }
    }
}
