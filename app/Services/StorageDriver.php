<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

interface StorageDriver
{
    public function createForUser(string $publicUserId): string;

    public function removeForUser(string $directoryName): void;

    /** @return list<array{name: string, type: string, size: int, modified_at: int}> */
    public function listContents(string $directoryName, string $folderName): array;

    public function createFolderForUser(string $directoryName, string $folderName): void;

    public function removeEmptyFolderForUser(string $directoryName, string $folderName): void;

    public function storeUploadedFile(
        string $directoryName,
        string $folderName,
        string $temporaryPath,
        string $fileName
    ): int;

    public function fileSizeForUser(string $directoryName, string $folderName, string $fileName): int;

    /** @param resource $destination */
    public function streamFileForUser(
        string $directoryName,
        string $folderName,
        string $fileName,
        $destination
    ): void;

    public function deleteFileForUser(string $directoryName, string $folderName, string $fileName): int;

    /** @return array{ready: bool, detail: string, fix: string} */
    public function readiness(PDO $pdo): array;
}
