<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StorageItemNotFound;
use App\Services\StorageProvisioner;
use App\Support\BuiltInApps;
use PDO;
use RuntimeException;

/** Media Viewer and Document Viewer: read-only views of files already in the user's drive. */
final class ViewerController
{
    private const MEDIA_TYPES = [
        'jpg' => ['image', 'image/jpeg'], 'jpeg' => ['image', 'image/jpeg'], 'png' => ['image', 'image/png'],
        'gif' => ['image', 'image/gif'], 'webp' => ['image', 'image/webp'], 'avif' => ['image', 'image/avif'],
        'mp4' => ['video', 'video/mp4'], 'webm' => ['video', 'video/webm'], 'm4v' => ['video', 'video/mp4'],
        'mov' => ['video', 'video/quicktime'],
        'mp3' => ['audio', 'audio/mpeg'], 'wav' => ['audio', 'audio/wav'], 'ogg' => ['audio', 'audio/ogg'],
        'm4a' => ['audio', 'audio/mp4'], 'aac' => ['audio', 'audio/aac'], 'flac' => ['audio', 'audio/flac'],
    ];

    private const DOCUMENT_TYPES = [
        'pdf' => ['pdf', 'application/pdf'],
        'txt' => ['text', 'text/plain'], 'md' => ['text', 'text/plain'], 'log' => ['text', 'text/plain'],
        'json' => ['text', 'text/plain'], 'xml' => ['text', 'text/plain'], 'csv' => ['csv', 'text/plain'],
    ];

    private const TEXT_PREVIEW_LIMIT = 1_000_000;

    public function media(): void
    {
        $user = BuiltInApps::requireAccess('media');
        $filter = (string) ($_GET['type'] ?? 'all');
        if (!in_array($filter, ['all', 'image', 'video', 'audio'], true)) {
            $filter = 'all';
        }
        $items = $this->collect((int) $user['id'], self::MEDIA_TYPES);
        $counts = ['all' => count($items), 'image' => 0, 'video' => 0, 'audio' => 0];
        foreach ($items as $item) {
            $counts[$item['kind']]++;
        }
        if ($filter !== 'all') {
            $items = array_values(array_filter($items, static fn (array $item): bool => $item['kind'] === $filter));
        }

        render('viewer/media', [
            'title' => 'Media Viewer',
            'user' => $user,
            'items' => $items,
            'selected' => $this->selected($items),
            'filter' => $filter,
            'counts' => $counts,
        ]);
    }

    public function documents(): void
    {
        $user = BuiltInApps::requireAccess('documents');
        $items = $this->collect((int) $user['id'], self::DOCUMENT_TYPES);
        $selected = $this->selected($items);
        $text = null;
        $table = null;
        $tooLarge = false;

        if ($selected !== null && $selected['kind'] !== 'pdf') {
            if ($selected['size'] > self::TEXT_PREVIEW_LIMIT) {
                $tooLarge = true;
            } else {
                $text = $this->readText((int) $user['id'], $selected['folder'], $selected['name']);
                if ($text !== null && $selected['kind'] === 'csv') {
                    $table = $this->csvRows($text);
                }
            }
        }

        render('viewer/documents', [
            'title' => 'Document Viewer',
            'user' => $user,
            'items' => $items,
            'selected' => $selected,
            'text' => $text,
            'table' => $table,
            'tooLarge' => $tooLarge,
        ]);
    }

    /** Streams a viewable file inline: GET /apps/file?folder=…&file=… */
    public function file(): void
    {
        $user = BuiltInApps::requireAccess('media');
        $folder = $_GET['folder'] ?? '';
        $name = $_GET['file'] ?? '';
        $types = self::MEDIA_TYPES + self::DOCUMENT_TYPES;
        $extension = is_string($name) ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) : '';
        if (!is_string($folder) || !is_string($name) || basename($name) !== $name || !isset($types[$extension]) || !$this->ownsFolder((int) $user['id'], $folder)) {
            $this->notFound();
        }

        $storageKey = $this->storageKey((int) $user['id']);
        $storage = new StorageProvisioner(dirname(__DIR__, 3));
        $buffer = fopen('php://temp', 'w+b');
        if ($buffer === false) {
            throw new RuntimeException('Unable to prepare the file.');
        }
        try {
            $size = $storage->fileSizeForUser($storageKey, $folder, $name);
            $storage->streamFileForUser($storageKey, $folder, $name, $buffer);
        } catch (StorageItemNotFound) {
            fclose($buffer);
            $this->notFound();
        }
        rewind($buffer);

        [$kind, $mime] = $types[$extension];
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $size);
        header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($name));
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        if ($kind === 'pdf') {
            // Allow the PDF to be shown in Document Viewer's frame on this site only.
            header('X-Frame-Options: SAMEORIGIN');
            header("Content-Security-Policy: frame-ancestors 'self'");
        }
        fpassthru($buffer);
        fclose($buffer);
    }

    /**
     * @param array<string, array{0: string, 1: string}> $types
     * @return list<array{folder: string, name: string, kind: string, size: int, modified_at: int, url: string}>
     */
    private function collect(int $userId, array $types): array
    {
        $pdo = app_db();
        $folders = $pdo->prepare('SELECT folder_name FROM storage_folders WHERE user_id = :user_id ORDER BY folder_name');
        $folders->execute(['user_id' => $userId]);
        $storageKey = $this->storageKey($userId);
        $storage = new StorageProvisioner(dirname(__DIR__, 3));

        $items = [];
        foreach ($folders->fetchAll(PDO::FETCH_COLUMN) as $folder) {
            try {
                $contents = $storage->listContents($storageKey, (string) $folder);
            } catch (RuntimeException) {
                continue;
            }
            foreach ($contents as $entry) {
                $extension = strtolower(pathinfo($entry['name'], PATHINFO_EXTENSION));
                if ($entry['type'] !== 'file' || !isset($types[$extension])) {
                    continue;
                }
                $items[] = [
                    'folder' => (string) $folder,
                    'name' => $entry['name'],
                    'kind' => $types[$extension][0],
                    'size' => (int) $entry['size'],
                    'modified_at' => (int) $entry['modified_at'],
                    'url' => '/apps/file?' . http_build_query(['folder' => $folder, 'file' => $entry['name']]),
                ];
            }
        }
        usort($items, static fn (array $a, array $b): int => $b['modified_at'] <=> $a['modified_at']);

        return $items;
    }

    /** @param list<array{folder: string, name: string}> $items */
    private function selected(array $items): ?array
    {
        $folder = $_GET['folder'] ?? null;
        $name = $_GET['file'] ?? null;
        foreach ($items as $item) {
            if ($item['folder'] === $folder && $item['name'] === $name) {
                return $item;
            }
        }

        return $items[0] ?? null;
    }

    private function readText(int $userId, string $folder, string $name): ?string
    {
        $buffer = fopen('php://temp', 'w+b');
        if ($buffer === false) {
            return null;
        }
        try {
            (new StorageProvisioner(dirname(__DIR__, 3)))->streamFileForUser($this->storageKey($userId), $folder, $name, $buffer);
        } catch (StorageItemNotFound) {
            fclose($buffer);
            return null;
        }
        rewind($buffer);
        $text = (string) stream_get_contents($buffer, self::TEXT_PREVIEW_LIMIT);
        fclose($buffer);

        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    /** @return list<list<string>> */
    private function csvRows(string $text): array
    {
        $rows = [];
        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            return [];
        }
        fwrite($handle, $text);
        rewind($handle);
        while (count($rows) < 500 && ($row = fgetcsv($handle, escape: '')) !== false) {
            $rows[] = array_map(static fn ($cell): string => (string) $cell, $row);
        }
        fclose($handle);

        return $rows;
    }

    private function ownsFolder(int $userId, string $folder): bool
    {
        $statement = app_db()->prepare('SELECT 1 FROM storage_folders WHERE user_id = :user_id AND folder_name = :folder');
        $statement->execute(['user_id' => $userId, 'folder' => $folder]);

        return $statement->fetchColumn() !== false;
    }

    private function storageKey(int $userId): string
    {
        $statement = app_db()->prepare('SELECT storage_key FROM storage_accounts WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $key = $statement->fetchColumn();
        if (!is_string($key) || $key === '') {
            throw new RuntimeException('Storage account is not configured.');
        }

        return $key;
    }

    private function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'File not found.';
        exit;
    }
}
