<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Env;
use PDO;
use RuntimeException;

final class StorageProvisioner
{
    public const DEFAULT_FOLDERS = ['System', 'Documents', 'Media', 'Apps', 'Widgets', 'Themes'];

    private readonly StorageDriver $driver;

    public function __construct(string $projectRoot)
    {
        $driver = strtolower(trim((string) Env::get('APP_STORAGE_DRIVER', 'local')));
        $this->driver = match ($driver) {
            'local' => new LocalStorageDriver($projectRoot),
            'webdav' => new WebDavStorageDriver(),
            default => throw new RuntimeException('Unsupported APP_STORAGE_DRIVER. Use local or webdav.'),
        };
    }

    public function createForUser(string $publicUserId): string
    {
        return $this->driver->createForUser($publicUserId);
    }

    public function removeForUser(string $directoryName): void
    {
        $this->driver->removeForUser($directoryName);
    }

    /** @return list<array{name: string, type: string, size: int, modified_at: int}> */
    public function listContents(string $directoryName, string $folderName): array
    {
        return $this->driver->listContents($directoryName, $folderName);
    }

    public function createFolderForUser(string $directoryName, string $folderName): void
    {
        $this->driver->createFolderForUser($directoryName, $folderName);
    }

    public function removeEmptyFolderForUser(string $directoryName, string $folderName): void
    {
        $this->driver->removeEmptyFolderForUser($directoryName, $folderName);
    }

    public function storeUploadedFile(
        string $directoryName,
        string $folderName,
        string $temporaryPath,
        string $fileName
    ): int {
        return $this->driver->storeUploadedFile($directoryName, $folderName, $temporaryPath, $fileName);
    }

    public function filePathForUser(string $directoryName, string $folderName, string $fileName): string
    {
        if (!$this->driver instanceof LocalStorageDriver) {
            throw new RuntimeException('A WebDAV file has no local filesystem path.');
        }

        return $this->driver->filePathForUser($directoryName, $folderName, $fileName);
    }

    public function fileSizeForUser(string $directoryName, string $folderName, string $fileName): int
    {
        return $this->driver->fileSizeForUser($directoryName, $folderName, $fileName);
    }

    /** @param resource $destination */
    public function streamFileForUser(
        string $directoryName,
        string $folderName,
        string $fileName,
        $destination
    ): void {
        $this->driver->streamFileForUser($directoryName, $folderName, $fileName, $destination);
    }

    public function deleteFileForUser(string $directoryName, string $folderName, string $fileName): int
    {
        return $this->driver->deleteFileForUser($directoryName, $folderName, $fileName);
    }

    /** @return array{ready: bool, detail: string, fix: string} */
    public function readiness(PDO $pdo): array
    {
        return $this->driver->readiness($pdo);
    }
}
