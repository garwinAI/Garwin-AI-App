<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StorageProvisioner;
use App\Services\StorageItemNotFound;
use App\Support\Csrf;
use PDO;
use PDOException;
use RuntimeException;

final class StorageController
{
    private const MAX_UPLOAD_BYTES = 104857600;

    private const DEFAULT_FOLDERS = StorageProvisioner::DEFAULT_FOLDERS;

    public function index(): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }

        $pdo = app_db();
        $this->ensureSharedDriveTables($pdo);
        $accountStatement = $pdo->prepare(
            'SELECT storage_key, quota_bytes, used_bytes FROM storage_accounts WHERE user_id = :user_id'
        );
        $accountStatement->execute(['user_id' => $user['id']]);
        $account = $accountStatement->fetch(PDO::FETCH_ASSOC);
        if ($account === false) {
            throw new \RuntimeException('Storage account is not configured.');
        }

        $folderStatement = $pdo->prepare(
            'SELECT folder_name FROM storage_folders WHERE user_id = :user_id ORDER BY folder_name'
        );
        $folderStatement->execute(['user_id' => $user['id']]);
        $folders = $folderStatement->fetchAll(PDO::FETCH_COLUMN);
        $teamMode = isset($_GET['team']) && ((string) $_GET['team']) === '1';
        $folderInput = $_GET['folder'] ?? '';
        $selectedFolder = is_string($folderInput) ? trim($folderInput) : '';
        $contents = [];
        $folderIsEmpty = false;
        if ($teamMode) {
            $selectedFolder = 'Shared Drive';
            $contents = $this->teamDriveContents($pdo, (int) $user['id']);
            $folderIsEmpty = $contents === [];
            $queryInput = $_GET['q'] ?? '';
            $query = is_string($queryInput) ? trim($queryInput) : '';
            if ($query !== '') {
                $contents = array_values(array_filter(
                    $contents,
                    static fn (array $entry): bool => mb_stripos((string) $entry['name'], $query) !== false
                ));
            }
        } elseif ($selectedFolder !== '') {
            if (!in_array($selectedFolder, $folders, true)) {
                redirect('/storage');
            }
            $contents = (new StorageProvisioner(dirname(__DIR__, 3)))->listContents(
                (string) $account['storage_key'],
                $selectedFolder
            );
            $folderIsEmpty = $contents === [];
            $queryInput = $_GET['q'] ?? '';
            $query = is_string($queryInput) ? trim($queryInput) : '';
            if ($query !== '') {
                $contents = array_values(array_filter(
                    $contents,
                    static fn (array $entry): bool => mb_stripos($entry['name'], $query) !== false
                ));
            }
        }

        $preferencesStatement = $pdo->prepare('SELECT preferences_json FROM user_preferences WHERE user_id = :user_id');
        $preferencesStatement->execute(['user_id' => $user['id']]);
        $preferences = json_decode((string) $preferencesStatement->fetchColumn(), true);

        $teamMembers = $this->teamMembers($pdo, (int) $user['id']);
        render('storage/index', [
            'title' => 'Storage',
            'user' => $user,
            'folders' => $folders,
            'selectedFolder' => $selectedFolder,
            'contents' => $contents,
            'account' => $account,
            'storageView' => is_array($preferences) ? ($preferences['storage_view'] ?? 'grid') : 'grid',
            'searchQuery' => is_string($_GET['q'] ?? null) ? (string) $_GET['q'] : '',
            'flashMessage' => take_flash_message(),
            'folderIsEmpty' => $folderIsEmpty,
            'teamMode' => $teamMode,
            'teamMembers' => $teamMembers,
        ]);
    }

    public function share(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }

        $folderInput = $_POST['folder'] ?? '';
        $fileInput = $_POST['file'] ?? '';
        $visibilityInput = $_POST['visibility'] ?? 'team';
        $allowedUsersInput = $_POST['allowed_users'] ?? [];
        $lockInput = $_POST['locked'] ?? '0';
        if (!is_string($folderInput) || !is_string($fileInput)
            || !is_string($visibilityInput) || !is_array($allowedUsersInput)
            || !is_string($lockInput)) {
            set_flash_message('error', 'Choose a file and permissions to update.');
            redirect('/storage');
        }

        $pdo = app_db();
        $this->ensureSharedDriveTables($pdo);
        $visibility = in_array($visibilityInput, ['team', 'private', 'shared'], true) ? $visibilityInput : 'team';
        $allowedUsers = [];
        foreach ($allowedUsersInput as $value) {
            $candidate = filter_var($value, FILTER_VALIDATE_INT);
            if ($candidate !== false && $candidate > 0) {
                $allowedUsers[] = (int) $candidate;
            }
        }
        $allowedUsers = array_values(array_unique($allowedUsers));
        $locked = ($lockInput === '1' || $lockInput === 'on') ? 1 : 0;

        $statement = $pdo->prepare(
            'INSERT INTO storage_file_permissions (user_id, folder_name, file_name, visibility, allowed_users_json, is_locked, created_at, updated_at)
             VALUES (:user_id, :folder_name, :file_name, :visibility, :allowed_users_json, :is_locked, :created_at, :updated_at)
             ON CONFLICT (user_id, folder_name, file_name) DO UPDATE SET
               visibility = excluded.visibility,
               allowed_users_json = excluded.allowed_users_json,
               is_locked = excluded.is_locked,
               updated_at = excluded.updated_at'
        );
        $statement->execute([
            'user_id' => (int) $user['id'],
            'folder_name' => trim($folderInput),
            'file_name' => trim($fileInput),
            'visibility' => $visibility,
            'allowed_users_json' => json_encode($allowedUsers, JSON_THROW_ON_ERROR),
            'is_locked' => $locked,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        set_flash_message('success', 'File sharing settings saved.');
        $redirectTarget = $folderInput === 'Shared Drive' ? '/storage?team=1' : '/storage?folder=' . rawurlencode($folderInput);
        redirect($redirectTarget);
    }

    public function createFolder(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }
        $nameInput = $_POST['name'] ?? '';
        if (!is_string($nameInput)) {
            set_flash_message('error', 'Enter a valid folder name.');
            redirect('/storage');
        }
        $name = trim($nameInput);
        $pdo = app_db();
        $account = $this->storageAccount($pdo, (int) $user['id']);
        $existing = $pdo->prepare(
            'SELECT 1 FROM storage_folders WHERE user_id = :user_id AND folder_name = :folder_name'
        );
        $existing->execute(['user_id' => $user['id'], 'folder_name' => $name]);
        if ($existing->fetchColumn() !== false) {
            set_flash_message('error', 'A folder with that name already exists.');
            redirect('/storage');
        }

        $storage = $this->storageProvisioner();
        $created = false;
        try {
            $pdo->beginTransaction();
            $insert = $pdo->prepare(
                'INSERT INTO storage_folders (user_id, folder_name, created_at)
                 VALUES (:user_id, :folder_name, :created_at)'
            );
            $insert->execute([
                'user_id' => $user['id'],
                'folder_name' => $name,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $storage->createFolderForUser((string) $account['storage_key'], $name);
            $created = true;
            $pdo->commit();
        } catch (RuntimeException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($created) {
                $storage->removeEmptyFolderForUser((string) $account['storage_key'], $name);
            }
            set_flash_message('error', $exception->getMessage());
            redirect('/storage');
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($created) {
                $storage->removeEmptyFolderForUser((string) $account['storage_key'], $name);
            }
            if (str_contains($exception->getMessage(), 'UNIQUE') || str_contains($exception->getMessage(), 'Duplicate')) {
                set_flash_message('error', 'A folder with that name already exists.');
                redirect('/storage');
            }
            throw $exception;
        }

        set_flash_message('success', 'Folder created.');
        redirect('/storage');
    }

    public function deleteFolder(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }
        $nameInput = $_POST['name'] ?? '';
        if (!is_string($nameInput)) {
            set_flash_message('error', 'Choose a valid folder.');
            redirect('/storage');
        }
        $name = trim($nameInput);
        if (in_array($name, self::DEFAULT_FOLDERS, true)) {
            set_flash_message('error', 'Default workspace folders cannot be deleted.');
            redirect('/storage?folder=' . rawurlencode($name));
        }
        $pdo = app_db();
        $account = $this->storageAccount($pdo, (int) $user['id']);
        $folder = $pdo->prepare(
            'SELECT 1 FROM storage_folders WHERE user_id = :user_id AND folder_name = :folder_name'
        );
        $folder->execute(['user_id' => $user['id'], 'folder_name' => $name]);
        if ($folder->fetchColumn() === false) {
            set_flash_message('error', 'Folder not found.');
            redirect('/storage');
        }

        try {
            $this->storageProvisioner()->removeEmptyFolderForUser((string) $account['storage_key'], $name);
            $delete = $pdo->prepare(
                'DELETE FROM storage_folders WHERE user_id = :user_id AND folder_name = :folder_name'
            );
            $delete->execute(['user_id' => $user['id'], 'folder_name' => $name]);
        } catch (RuntimeException $exception) {
            set_flash_message('error', $exception->getMessage());
            redirect('/storage?folder=' . rawurlencode($name));
        }

        set_flash_message('success', 'Empty folder deleted.');
        redirect('/storage');
    }

    public function upload(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }
        $folderInput = $_POST['folder'] ?? '';
        $upload = $_FILES['upload'] ?? null;
        if (!is_string($folderInput) || !is_array($upload)
            || !isset($upload['name'], $upload['tmp_name'], $upload['error'], $upload['size'])
            || !is_string($upload['name']) || !is_string($upload['tmp_name'])
            || !is_int($upload['error']) || !is_int($upload['size'])) {
            set_flash_message('error', 'Choose a file and destination folder.');
            redirect('/storage');
        }
        $folderName = trim($folderInput);
        if (!$this->folderExists(app_db(), (int) $user['id'], $folderName)) {
            set_flash_message('error', 'Choose a valid storage folder.');
            redirect('/storage');
        }
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            set_flash_message('error', $this->uploadError($upload['error']));
            redirect('/storage?folder=' . rawurlencode($folderName));
        }
        if ($upload['size'] < 0 || $upload['size'] > self::MAX_UPLOAD_BYTES) {
            set_flash_message('error', 'Files must be 100 MB or smaller.');
            redirect('/storage?folder=' . rawurlencode($folderName));
        }
        if (!is_uploaded_file($upload['tmp_name'])) {
            set_flash_message('error', 'The uploaded file could not be verified.');
            redirect('/storage?folder=' . rawurlencode($folderName));
        }

        $pdo = app_db();
        $account = $this->storageAccount($pdo, (int) $user['id']);
        $size = filesize($upload['tmp_name']);
        if ($size === false || $size !== $upload['size']) {
            set_flash_message('error', 'The uploaded file could not be verified.');
            redirect('/storage?folder=' . rawurlencode($folderName));
        }
        if ($size > 0) {
            $reserve = $pdo->prepare(
                'UPDATE storage_accounts
                 SET used_bytes = used_bytes + :reserve_size, updated_at = :updated_at
                 WHERE user_id = :user_id AND used_bytes + :quota_size <= quota_bytes'
            );
            $reserve->execute([
                'reserve_size' => $size,
                'quota_size' => $size,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'user_id' => $user['id'],
            ]);
            if ($reserve->rowCount() !== 1) {
                set_flash_message('error', 'This file exceeds your available storage quota.');
                redirect('/storage?folder=' . rawurlencode($folderName));
            }
        }

        try {
            $storedSize = $this->storageProvisioner()->storeUploadedFile(
                (string) $account['storage_key'],
                $folderName,
                $upload['tmp_name'],
                $upload['name']
            );
            if ($storedSize !== $size) {
                $this->storageProvisioner()->deleteFileForUser(
                    (string) $account['storage_key'],
                    $folderName,
                    $upload['name']
                );
                throw new RuntimeException('The uploaded file size changed while being stored.');
            }
        } catch (RuntimeException $exception) {
            if ($size > 0) {
                $this->releaseQuota($pdo, (int) $user['id'], $size);
            }
            set_flash_message('error', $exception->getMessage());
            redirect('/storage?folder=' . rawurlencode($folderName));
        } catch (\Throwable $exception) {
            if ($size > 0) {
                $this->releaseQuota($pdo, (int) $user['id'], $size);
            }
            throw $exception;
        }

        set_flash_message('success', 'File uploaded.');
        redirect('/storage?folder=' . rawurlencode($folderName));
    }

    public function download(): void
    {
        $user = $this->requireWorkspaceUser();
        $folderInput = $_GET['folder'] ?? '';
        $fileInput = $_GET['file'] ?? '';
        if (!is_string($folderInput) || !is_string($fileInput)) {
            http_response_code(404);
            echo 'File not found.';
            return;
        }
        $pdo = app_db();
        $this->ensureSharedDriveTables($pdo);
        if ($folderInput === 'Shared Drive') {
            $row = $this->sharedFilePermission($pdo, (int) $user['id'], $fileInput);
            if ($row === null) {
                http_response_code(404);
                echo 'File not found.';
                return;
            }
            $account = $this->storageAccount($pdo, (int) $row['user_id']);
            $storage = $this->storageProvisioner();
            $temporaryDownload = fopen('php://temp', 'w+b');
            if ($temporaryDownload === false) {
                throw new RuntimeException('Unable to prepare the storage file for download.');
            }
            try {
                $size = $storage->fileSizeForUser(
                    (string) $account['storage_key'],
                    (string) $row['folder_name'],
                    $fileInput
                );
                $storage->streamFileForUser(
                    (string) $account['storage_key'],
                    (string) $row['folder_name'],
                    $fileInput,
                    $temporaryDownload
                );
            } catch (StorageItemNotFound) {
                fclose($temporaryDownload);
                http_response_code(404);
                echo 'File not found.';
                return;
            }
            if (!rewind($temporaryDownload)) {
                fclose($temporaryDownload);
                throw new RuntimeException('Unable to prepare the storage file for download.');
            }
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($fileInput));
            header('Content-Length: ' . $size);
            header('Cache-Control: private, no-store');
            header('X-Content-Type-Options: nosniff');
            fpassthru($temporaryDownload);
            fclose($temporaryDownload);
            return;
        }
        if (!$this->folderExists($pdo, (int) $user['id'], $folderInput)) {
            http_response_code(404);
            echo 'File not found.';
            return;
        }
        $account = $this->storageAccount($pdo, (int) $user['id']);
        $storage = $this->storageProvisioner();
        $temporaryDownload = fopen('php://temp', 'w+b');
        if ($temporaryDownload === false) {
            throw new RuntimeException('Unable to prepare the storage file for download.');
        }
        try {
            $size = $storage->fileSizeForUser(
                (string) $account['storage_key'],
                $folderInput,
                $fileInput
            );
            $storage->streamFileForUser(
                (string) $account['storage_key'],
                $folderInput,
                $fileInput,
                $temporaryDownload
            );
        } catch (StorageItemNotFound) {
            fclose($temporaryDownload);
            http_response_code(404);
            echo 'File not found.';
            return;
        }
        if (!rewind($temporaryDownload)) {
            fclose($temporaryDownload);
            throw new RuntimeException('Unable to prepare the storage file for download.');
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename*=UTF-8\'\'' . rawurlencode($fileInput));
        header('Content-Length: ' . $size);
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        fpassthru($temporaryDownload);
        fclose($temporaryDownload);
    }

    public function deleteFile(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }
        $folderInput = $_POST['folder'] ?? '';
        $fileInput = $_POST['file'] ?? '';
        if (!is_string($folderInput) || !is_string($fileInput)) {
            set_flash_message('error', 'Choose a valid storage file.');
            redirect('/storage');
        }
        $pdo = app_db();
        $this->ensureSharedDriveTables($pdo);
        if ($folderInput === 'Shared Drive') {
            $row = $this->sharedFilePermission($pdo, (int) $user['id'], $fileInput);
            if ($row === null || !$this->canEditFile($pdo, (int) $user['id'], $row)) {
                set_flash_message('error', 'That shared file is locked or not editable by your account.');
                redirect('/storage?team=1');
            }
            $ownerUserId = (int) $row['user_id'];
            $account = $this->storageAccount($pdo, $ownerUserId);
            $storage = $this->storageProvisioner();
            try {
                $size = $storage->fileSizeForUser((string) $account['storage_key'], (string) $row['folder_name'], $fileInput);
                if ($size > 0) {
                    $this->releaseQuota($pdo, $ownerUserId, $size);
                }
                try {
                    $storage->deleteFileForUser((string) $account['storage_key'], (string) $row['folder_name'], $fileInput);
                } catch (RuntimeException $exception) {
                    if ($size > 0) {
                        $this->addQuota($pdo, $ownerUserId, $size);
                    }
                    throw $exception;
                }
            } catch (RuntimeException $exception) {
                set_flash_message('error', $exception->getMessage());
                redirect('/storage?team=1');
            }

            $pdo->prepare('DELETE FROM storage_file_permissions WHERE user_id = :user_id AND folder_name = :folder_name AND file_name = :file_name')
                ->execute(['user_id' => $ownerUserId, 'folder_name' => $row['folder_name'], 'file_name' => $fileInput]);
            set_flash_message('success', 'Shared file deleted.');
            redirect('/storage?team=1');
        }
        if (!$this->folderExists($pdo, (int) $user['id'], $folderInput)) {
            set_flash_message('error', 'Choose a valid storage file.');
            redirect('/storage');
        }
        $account = $this->storageAccount($pdo, (int) $user['id']);
        $storage = $this->storageProvisioner();
        try {
            $size = $storage->fileSizeForUser((string) $account['storage_key'], $folderInput, $fileInput);
            if ($size > 0) {
                $this->releaseQuota($pdo, (int) $user['id'], $size);
            }
            try {
                $storage->deleteFileForUser((string) $account['storage_key'], $folderInput, $fileInput);
            } catch (RuntimeException $exception) {
                if ($size > 0) {
                    $this->addQuota($pdo, (int) $user['id'], $size);
                }
                throw $exception;
            }
        } catch (RuntimeException $exception) {
            set_flash_message('error', $exception->getMessage());
            redirect('/storage?folder=' . rawurlencode($folderInput));
        }

        set_flash_message('success', 'File deleted.');
        redirect('/storage?folder=' . rawurlencode($folderInput));
    }

    private function ensureSharedDriveTables(PDO $pdo): void
    {
        $driver = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $statements = match ($driver) {
            'sqlite' => [
                'CREATE TABLE IF NOT EXISTS shared_drive_members (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
                    team_name TEXT NOT NULL DEFAULT "team-drive",
                    created_at TEXT NOT NULL
                )',
                'CREATE TABLE IF NOT EXISTS storage_file_permissions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    folder_name TEXT NOT NULL,
                    file_name TEXT NOT NULL,
                    visibility TEXT NOT NULL DEFAULT "team",
                    allowed_users_json TEXT NOT NULL DEFAULT "[]",
                    is_locked INTEGER NOT NULL DEFAULT 0,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    UNIQUE (user_id, folder_name, file_name)
                )',
            ],
            'mysql' => [
                'CREATE TABLE IF NOT EXISTS shared_drive_members (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
                    team_name VARCHAR(64) NOT NULL DEFAULT "team-drive",
                    created_at DATETIME NOT NULL,
                    CONSTRAINT shared_drive_members_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                'CREATE TABLE IF NOT EXISTS storage_file_permissions (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id BIGINT UNSIGNED NOT NULL,
                    folder_name VARCHAR(128) NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    visibility VARCHAR(16) NOT NULL DEFAULT "team",
                    allowed_users_json JSON NOT NULL,
                    is_locked TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY storage_file_perm_unique (user_id, folder_name, file_name),
                    CONSTRAINT storage_file_perm_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            ],
            'pgsql' => [
                'CREATE TABLE IF NOT EXISTS shared_drive_members (
                    id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
                    team_name VARCHAR(64) NOT NULL DEFAULT "team-drive",
                    created_at TIMESTAMP NOT NULL
                )',
                'CREATE TABLE IF NOT EXISTS storage_file_permissions (
                    id BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
                    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                    folder_name VARCHAR(128) NOT NULL,
                    file_name VARCHAR(255) NOT NULL,
                    visibility VARCHAR(16) NOT NULL DEFAULT "team",
                    allowed_users_json JSONB NOT NULL DEFAULT "[]"::jsonb,
                    is_locked SMALLINT NOT NULL DEFAULT 0,
                    created_at TIMESTAMP NOT NULL,
                    updated_at TIMESTAMP NOT NULL,
                    UNIQUE (user_id, folder_name, file_name)
                )',
            ],
            default => [],
        };

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
    }

    /** @return list<array{id:int,name:string,email:string}> */
    private function teamMembers(PDO $pdo, int $userId): array
    {
        $this->ensureSharedDriveTables($pdo);
        $teamStatement = $pdo->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email
             FROM shared_drive_members s
             JOIN users u ON u.id = s.user_id
             WHERE s.team_name = :team_name ORDER BY u.first_name, u.last_name, u.id'
        );
        $teamStatement->execute(['team_name' => 'team-drive']);
        $members = $teamStatement->fetchAll(PDO::FETCH_ASSOC);
        if ($members !== []) {
            return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => trim((string) $row['first_name'] . ' ' . $row['last_name']), 'email' => (string) $row['email']], $members);
        }

        $allStatement = $pdo->query(
            'SELECT id, first_name, last_name, email FROM users ORDER BY first_name, last_name, id'
        );
        $allUsers = $allStatement->fetchAll(PDO::FETCH_ASSOC);
        $insert = $pdo->prepare('INSERT OR IGNORE INTO shared_drive_members (user_id, team_name, created_at) VALUES (:user_id, :team_name, :created_at)');
        foreach ($allUsers as $row) {
            $insert->execute(['user_id' => (int) $row['id'], 'team_name' => 'team-drive', 'created_at' => gmdate('Y-m-d H:i:s')]);
        }

        return array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'name' => trim((string) $row['first_name'] . ' ' . $row['last_name']), 'email' => (string) $row['email']], $allUsers);
    }

    /** @return list<array{name:string, owner_name:string, owner_email:string, size:int, modified_at:int, visibility:string, allowed_users:list<int>, locked:bool}> */
    private function teamDriveContents(PDO $pdo, int $viewerUserId): array
    {
        $this->ensureSharedDriveTables($pdo);
        $memberIds = array_map(static fn (array $member): int => (int) $member['id'], $this->teamMembers($pdo, $viewerUserId));
        if ($memberIds === []) {
            return [];
        }

        $memberIdsPlaceholder = implode(',', array_fill(0, count($memberIds), '?'));
        $statement = $pdo->prepare(
            'SELECT p.user_id, p.folder_name, p.file_name, p.visibility, p.allowed_users_json, p.is_locked,
                    u.first_name, u.last_name
             FROM storage_file_permissions p
             JOIN users u ON u.id = p.user_id
             WHERE p.user_id IN (' . $memberIdsPlaceholder . ')
             ORDER BY p.user_id, p.folder_name, p.file_name'
        );
        $statement->execute($memberIds);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $contents = [];
        foreach ($rows as $row) {
            $ownerUserId = (int) $row['user_id'];
            $permission = $this->sharedFilePermissionPayload($row);
            if (!$this->canViewFile($pdo, $viewerUserId, $ownerUserId, $permission)) {
                continue;
            }
            $storageKey = $this->storageAccount($pdo, $ownerUserId)['storage_key'];
            $folderName = (string) $row['folder_name'];
            $fileName = (string) $row['file_name'];
            $path = $this->storageProvisioner()->filePathForUser((string) $storageKey, $folderName, $fileName);
            if (!is_file($path)) {
                continue;
            }
            $contents[] = [
                'name' => $fileName,
                'owner_name' => trim((string) $row['first_name'] . ' ' . $row['last_name']),
                'owner_email' => '',
                'size' => (int) filesize($path),
                'modified_at' => (int) filemtime($path),
                'visibility' => $permission['visibility'],
                'allowed_users' => $permission['allowed_users'],
                'locked' => $permission['locked'],
                'owner_user_id' => $ownerUserId,
                'folder_name' => $folderName,
            ];
        }

        return $contents;
    }

    private function sharedFilePermission(PDO $pdo, int $viewerUserId, string $fileName): ?array
    {
        $statement = $pdo->prepare(
            'SELECT p.user_id, p.folder_name, p.file_name, p.visibility, p.allowed_users_json, p.is_locked,
                    u.first_name, u.last_name, u.email
             FROM storage_file_permissions p
             JOIN users u ON u.id = p.user_id
             WHERE p.file_name = :file_name AND p.user_id IN (
                 SELECT user_id FROM shared_drive_members WHERE team_name = :team_name
             )
             ORDER BY p.updated_at DESC LIMIT 1'
        );
        $statement->execute(['file_name' => trim($fileName), 'team_name' => 'team-drive']);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $permission = $this->sharedFilePermissionPayload($row);
        if (!$this->canViewFile($pdo, $viewerUserId, (int) $row['user_id'], $permission)) {
            return null;
        }

        return [
            'user_id' => (int) $row['user_id'],
            'folder_name' => (string) $row['folder_name'],
            'file_name' => (string) $row['file_name'],
            'visibility' => $permission['visibility'],
            'allowed_users' => $permission['allowed_users'],
            'locked' => $permission['locked'],
        ];
    }

    /** @return array{visibility:string, allowed_users:list<int>, locked:bool} */
    private function sharedFilePermissionPayload(array $row): array
    {
        $allowed = json_decode((string) ($row['allowed_users_json'] ?? '[]'), true);
        $allowedUsers = is_array($allowed) ? array_values(array_filter(array_map(static fn ($value): int => is_numeric((string) $value) ? (int) $value : 0, $allowed), static fn (int $id): bool => $id > 0)) : [];

        return [
            'visibility' => in_array((string) ($row['visibility'] ?? 'team'), ['team', 'private', 'shared'], true) ? (string) $row['visibility'] : 'team',
            'allowed_users' => $allowedUsers,
            'locked' => ((int) ($row['is_locked'] ?? 0)) === 1,
        ];
    }

    private function canViewFile(PDO $pdo, int $viewerUserId, int $ownerUserId, array $permission): bool
    {
        if ($viewerUserId === $ownerUserId) {
            return true;
        }
        if (($permission['visibility'] ?? 'team') === 'private') {
            return false;
        }
        if (($permission['visibility'] ?? 'team') === 'team') {
            $teamStatement = $pdo->prepare('SELECT 1 FROM shared_drive_members WHERE user_id = :user_id AND team_name = :team_name LIMIT 1');
            $teamStatement->execute(['user_id' => $viewerUserId, 'team_name' => 'team-drive']);
            return $teamStatement->fetchColumn() !== false;
        }

        return in_array($viewerUserId, $permission['allowed_users'] ?? [], true);
    }

    private function canEditFile(PDO $pdo, int $viewerUserId, array $row): bool
    {
        if ((int) $row['user_id'] === $viewerUserId) {
            return true;
        }
        $permission = $this->sharedFilePermissionPayload($row);
        if ($permission['locked']) {
            return false;
        }
        if ($permission['visibility'] === 'team') {
            $teamStatement = $pdo->prepare('SELECT 1 FROM shared_drive_members WHERE user_id = :user_id AND team_name = :team_name LIMIT 1');
            $teamStatement->execute(['user_id' => $viewerUserId, 'team_name' => 'team-drive']);
            return $teamStatement->fetchColumn() !== false;
        }

        return in_array($viewerUserId, $permission['allowed_users'], true);
    }

    private function requireWorkspaceUser(): array
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

    private function validateCsrf(): bool
    {
        $token = $_POST['_token'] ?? null;
        if (Csrf::isValid(is_string($token) ? $token : null)) {
            return true;
        }
        render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);

        return false;
    }

    private function storageAccount(PDO $pdo, int $userId): array
    {
        $statement = $pdo->prepare(
            'SELECT storage_key, quota_bytes, used_bytes FROM storage_accounts WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
        $account = $statement->fetch(PDO::FETCH_ASSOC);
        if ($account === false) {
            throw new RuntimeException('Storage account is not configured.');
        }

        return $account;
    }

    private function folderExists(PDO $pdo, int $userId, string $folderName): bool
    {
        $statement = $pdo->prepare(
            'SELECT 1 FROM storage_folders WHERE user_id = :user_id AND folder_name = :folder_name'
        );
        $statement->execute(['user_id' => $userId, 'folder_name' => $folderName]);

        return $statement->fetchColumn() !== false;
    }

    private function releaseQuota(PDO $pdo, int $userId, int $size): void
    {
        if ($size === 0) {
            return;
        }
        $statement = $pdo->prepare(
            'UPDATE storage_accounts
             SET used_bytes = CASE WHEN used_bytes >= :check_size THEN used_bytes - :subtract_size ELSE 0 END,
                 updated_at = :updated_at WHERE user_id = :user_id'
        );
        $statement->execute([
            'check_size' => $size,
            'subtract_size' => $size,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
        if ($statement->rowCount() === 0) {
            throw new RuntimeException('Unable to update storage usage.');
        }
    }

    private function addQuota(PDO $pdo, int $userId, int $size): void
    {
        $statement = $pdo->prepare(
            'UPDATE storage_accounts SET used_bytes = used_bytes + :size, updated_at = :updated_at
             WHERE user_id = :user_id'
        );
        $statement->execute([
            'size' => $size,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
        if ($statement->rowCount() === 0) {
            throw new RuntimeException('Unable to restore storage usage after a failed delete.');
        }
    }

    private function storageProvisioner(): StorageProvisioner
    {
        return new StorageProvisioner(dirname(__DIR__, 3));
    }

    private function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the server upload limit.',
            UPLOAD_ERR_PARTIAL => 'The file upload was incomplete. Try again.',
            UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not store this upload.',
            default => 'The upload failed. Try again.',
        };
    }
}
