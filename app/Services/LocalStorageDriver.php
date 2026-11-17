<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Env;
use RuntimeException;
use FilesystemIterator;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class LocalStorageDriver implements StorageDriver
{
    private const MASTER_DIRECTORY = 'ACSD-73h5ZXp1Q';

    private readonly string $root;

    public function __construct(string $projectRoot)
    {
        $configured = Env::get('GARWIN_STORAGE_PATH', 'var/private-storage');
        $path = str_starts_with($configured, DIRECTORY_SEPARATOR)
            ? $configured
            : rtrim($projectRoot, '/') . '/' . $configured;
        $this->root = rtrim($path, '/') . '/' . self::MASTER_DIRECTORY;
    }

    public function createForUser(string $publicUserId): string
    {
        if (!preg_match('/\A[A-Za-z0-9]{9}\z/', $publicUserId)) {
            throw new RuntimeException('Invalid public user ID for storage provisioning.');
        }

        $this->ensurePrivateRoot();
        $directoryName = 'UCSD-' . $publicUserId . '-001';
        $userPath = $this->root . '/' . $directoryName;

        if (!mkdir($userPath, 0700)) {
            throw new RuntimeException('Unable to create the private user storage directory.');
        }

        try {
            foreach (StorageProvisioner::DEFAULT_FOLDERS as $folder) {
                if (!mkdir($userPath . '/' . $folder, 0700)) {
                    throw new RuntimeException('Unable to create a default user storage folder.');
                }
            }
        } catch (\Throwable $exception) {
            $this->removeTree($userPath);
            throw $exception;
        }

        return $directoryName;
    }

    public function removeForUser(string $directoryName): void
    {
        if (!preg_match('/\AUCSD-[A-Za-z0-9]{9}-001\z/', $directoryName)) {
            throw new RuntimeException('Invalid private storage directory key.');
        }

        $this->removeTree($this->root . '/' . $directoryName);
    }

    /** @return list<array{name: string, type: string, size: int, modified_at: int}> */
    public function listContents(string $directoryName, string $folderName): array
    {
        $resolvedFolder = $this->folderPath($directoryName, $folderName);

        $entries = scandir($resolvedFolder);
        if ($entries === false) {
            throw new RuntimeException('Unable to read the private storage folder.');
        }

        $contents = [];
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $resolvedFolder . DIRECTORY_SEPARATOR . $name;
            if (is_link($path)) {
                continue;
            }
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            $size = is_file($path) ? filesize($path) : 0;
            $modifiedAt = filemtime($path);
            if ($size === false || $modifiedAt === false) {
                throw new RuntimeException('Unable to read private storage item metadata.');
            }
            $contents[] = [
                'name' => $name,
                'type' => is_dir($path) ? 'folder' : 'file',
                'size' => $size,
                'modified_at' => $modifiedAt,
            ];
        }

        usort($contents, static fn (array $left, array $right): int =>
            [$left['type'] === 'folder' ? 0 : 1, mb_strtolower($left['name'])]
            <=> [$right['type'] === 'folder' ? 0 : 1, mb_strtolower($right['name'])]
        );

        return $contents;
    }

    public function createFolderForUser(string $directoryName, string $folderName): void
    {
        $this->validateFolderName($folderName);
        $userPath = $this->userPath($directoryName);
        $path = $userPath . DIRECTORY_SEPARATOR . $folderName;
        if (file_exists($path) || is_link($path)) {
            throw new RuntimeException('A folder with that name already exists.');
        }
        if (!mkdir($path, 0700)) {
            throw new RuntimeException('Unable to create the storage folder.');
        }
        if (!chmod($path, 0700)) {
            rmdir($path);
            throw new RuntimeException('Unable to secure the storage folder permissions.');
        }
    }

    public function removeEmptyFolderForUser(string $directoryName, string $folderName): void
    {
        $path = $this->folderPath($directoryName, $folderName);
        $entries = scandir($path);
        if ($entries === false) {
            throw new RuntimeException('Unable to read the storage folder.');
        }
        if (count($entries) > 2) {
            throw new RuntimeException('Only empty folders can be deleted.');
        }
        if (!rmdir($path)) {
            throw new RuntimeException('Unable to delete the storage folder.');
        }
    }

    public function storeUploadedFile(
        string $directoryName,
        string $folderName,
        string $temporaryPath,
        string $fileName
    ): int {
        $this->validateFileName($fileName);
        if (!is_uploaded_file($temporaryPath)) {
            throw new RuntimeException('The uploaded file could not be verified.');
        }
        $folderPath = $this->folderPath($directoryName, $folderName);
        $target = $folderPath . DIRECTORY_SEPARATOR . $fileName;
        $source = fopen($temporaryPath, 'rb');
        if ($source === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }
        $destination = fopen($target, 'xb');
        if ($destination === false) {
            fclose($source);
            throw new RuntimeException('An item with that name already exists or storage is unavailable.');
        }

        $copied = stream_copy_to_stream($source, $destination);
        fclose($source);
        fclose($destination);
        if ($copied === false || !chmod($target, 0600)) {
            unlink($target);
            throw new RuntimeException('Unable to store the uploaded file securely.');
        }

        return $copied;
    }

    public function filePathForUser(string $directoryName, string $folderName, string $fileName): string
    {
        $this->validateFileName($fileName);
        $folderPath = $this->folderPath($directoryName, $folderName);
        $path = $folderPath . DIRECTORY_SEPARATOR . $fileName;
        $resolved = realpath($path);
        if ($resolved === false || is_link($path) || !is_file($resolved)
            || !str_starts_with($resolved, $folderPath . DIRECTORY_SEPARATOR)) {
            throw new StorageItemNotFound('The requested storage file is unavailable.');
        }

        return $resolved;
    }

    public function fileSizeForUser(string $directoryName, string $folderName, string $fileName): int
    {
        $size = filesize($this->filePathForUser($directoryName, $folderName, $fileName));
        if ($size === false) {
            throw new RuntimeException('Unable to read the storage file size.');
        }

        return $size;
    }

    /** @param resource $destination */
    public function streamFileForUser(
        string $directoryName,
        string $folderName,
        string $fileName,
        $destination
    ): void {
        $source = fopen($this->filePathForUser($directoryName, $folderName, $fileName), 'rb');
        if ($source === false) {
            throw new RuntimeException('Unable to prepare the storage file for download.');
        }

        $copied = stream_copy_to_stream($source, $destination);
        fclose($source);
        if ($copied === false) {
            throw new RuntimeException('Unable to read the storage file for download.');
        }
    }

    public function deleteFileForUser(string $directoryName, string $folderName, string $fileName): int
    {
        $path = $this->filePathForUser($directoryName, $folderName, $fileName);
        $size = filesize($path);
        if ($size === false || !unlink($path)) {
            throw new RuntimeException('Unable to delete the storage file.');
        }

        return $size;
    }

    private function folderPath(string $directoryName, string $folderName): string
    {
        $this->validateFolderName($folderName);
        $userPath = $this->userPath($directoryName);
        $folderPath = $userPath . DIRECTORY_SEPARATOR . $folderName;
        $resolvedFolder = realpath($folderPath);
        if ($resolvedFolder === false || is_link($folderPath) || !is_dir($resolvedFolder)
            || !str_starts_with($resolvedFolder, $userPath . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The requested private storage folder is unavailable.');
        }

        return $resolvedFolder;
    }

    private function userPath(string $directoryName): string
    {
        if (!preg_match('/\AUCSD-[A-Za-z0-9]{9}-001\z/', $directoryName)) {
            throw new RuntimeException('Invalid private storage location.');
        }
        $resolvedRoot = realpath($this->root);
        $userPath = $this->root . DIRECTORY_SEPARATOR . $directoryName;
        $resolvedUser = realpath($userPath);
        if ($resolvedRoot === false || $resolvedUser === false || is_link($this->root) || is_link($userPath)
            || !is_dir($resolvedUser) || !str_starts_with($resolvedUser, $resolvedRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The requested private storage area is unavailable.');
        }

        return $resolvedUser;
    }

    private function validateFolderName(string $folderName): void
    {
        if (!preg_match('/\A[A-Za-z0-9 _-]{1,64}\z/', $folderName) || $folderName === '.' || $folderName === '..') {
            throw new RuntimeException('Folder names may contain letters, numbers, spaces, hyphens, and underscores only.');
        }
    }

    private function validateFileName(string $fileName): void
    {
        if ($fileName === '' || strlen($fileName) > 255 || $fileName === '.' || $fileName === '..'
            || str_contains($fileName, '/') || str_contains($fileName, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $fileName)) {
            throw new RuntimeException('That file name is not allowed.');
        }
    }

    /** @return array{ready: bool, detail: string, fix: string} */
    public function readiness(PDO $pdo): array
    {
        if (!is_dir($this->root) || is_link($this->root)) {
            return [
                'ready' => false,
                'detail' => 'The private ACSD storage root is missing or is a symlink.',
                'fix' => 'Set GARWIN_STORAGE_PATH to the private storage parent in the server environment. Restore the existing ACSD-73h5ZXp1Q directory if this installation already contains user files; for a new installation, provision it outside public/ with PHP-user ownership and mode 0700.',
            ];
        }

        $resolvedRoot = realpath($this->root);
        $publicRoot = realpath(dirname(__DIR__, 2) . '/public');
        if ($resolvedRoot === false || $publicRoot === false
            || $resolvedRoot === $publicRoot
            || str_starts_with($resolvedRoot, $publicRoot . DIRECTORY_SEPARATOR)) {
            return [
                'ready' => false,
                'detail' => 'Private storage must resolve outside the public web root.',
                'fix' => 'Change GARWIN_STORAGE_PATH to a private directory outside public/, then restore or move the existing ACSD-73h5ZXp1Q data there.',
            ];
        }

        if (!is_writable($resolvedRoot)) {
            return [
                'ready' => false,
                'detail' => 'PHP cannot write to the private storage root.',
                'fix' => 'Grant the PHP/web-server user ownership or write access to the ACSD-73h5ZXp1Q directory, then restrict its permissions to mode 0700.',
            ];
        }

        $permissions = fileperms($resolvedRoot);
        if ($permissions === false || ($permissions & 0077) !== 0) {
            return [
                'ready' => false,
                'detail' => 'Private storage permissions allow group or other access.',
                'fix' => 'Restrict the ACSD-73h5ZXp1Q directory to its PHP owner with chmod 0700.',
            ];
        }

        $missingDirectories = 0;
        $accounts = $pdo->query(
            'SELECT u.user_id, s.storage_key
             FROM users u
             LEFT JOIN storage_accounts s ON s.user_id = u.id'
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($accounts as $account) {
            $expectedKey = 'UCSD-' . $account['user_id'] . '-001';
            $userPath = $resolvedRoot . '/' . $expectedKey;
            $userPermissions = is_dir($userPath) && !is_link($userPath) ? fileperms($userPath) : false;
            $accountReady = is_string($account['storage_key'])
                && hash_equals($expectedKey, $account['storage_key'])
                && $userPermissions !== false
                && ($userPermissions & 0077) === 0
                && is_writable($userPath);
            foreach (StorageProvisioner::DEFAULT_FOLDERS as $folder) {
                $folderPath = $userPath . '/' . $folder;
                $folderPermissions = is_dir($folderPath) && !is_link($folderPath)
                    ? fileperms($folderPath)
                    : false;
                $accountReady = $accountReady
                    && $folderPermissions !== false
                    && ($folderPermissions & 0077) === 0;
            }

            if (!$accountReady) {
                $missingDirectories++;
            }
        }

        if ($missingDirectories > 0) {
            $detail = $missingDirectories === 1
                ? 'One account storage area is missing or fails ownership and permission checks.'
                : "{$missingDirectories} account storage areas are missing or fail ownership and permission checks.";

            return [
                'ready' => false,
                'detail' => $detail,
                'fix' => 'Set GARWIN_STORAGE_PATH to the location containing the existing ACSD-73h5ZXp1Q data, or restore missing user directories from backup. Do not initialize a new empty storage root over existing accounts.',
            ];
        }

        return [
            'ready' => true,
            'detail' => 'Private storage is writable, access-restricted, outside public/, and all account folders are present.',
            'fix' => 'No action required.',
        ];
    }

    private function ensurePrivateRoot(): void
    {
        if (is_link($this->root)) {
            throw new RuntimeException('Private storage root must not be a symlink.');
        }
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new RuntimeException('Unable to create the private storage root.');
        }

        $resolvedRoot = realpath($this->root);
        $publicRoot = realpath(dirname(__DIR__, 2) . '/public');
        if ($resolvedRoot === false || $publicRoot === false) {
            throw new RuntimeException('Unable to resolve the private storage location.');
        }

        if ($resolvedRoot === $publicRoot || str_starts_with($resolvedRoot, $publicRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Private storage must be outside the public web root.');
        }

        if (!chmod($resolvedRoot, 0700)) {
            throw new RuntimeException('Unable to secure the private storage root permissions.');
        }
    }

    private function removeTree(string $path): void
    {
        if (is_link($path)) {
            if (!unlink($path)) {
                throw new RuntimeException('Unable to remove a storage symlink.');
            }

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if ($entry->isLink() || !$entry->isDir()) {
                if (!unlink($entry->getPathname())) {
                    throw new RuntimeException('Unable to clean up private storage.');
                }
            } elseif (!rmdir($entry->getPathname())) {
                throw new RuntimeException('Unable to clean up private storage.');
            }
        }

        if (!rmdir($path)) {
            throw new RuntimeException('Unable to clean up private storage.');
        }
    }
}
