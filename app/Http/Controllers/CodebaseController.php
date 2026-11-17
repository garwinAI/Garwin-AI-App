<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\BuiltInApps;
use App\Support\Csrf;
use PDO;

final class CodebaseController
{
    private const MAX_BYTES = 500_000;

    /** File extension => language label */
    public const LANGUAGES = [
        'php' => 'PHP', 'js' => 'JavaScript', 'ts' => 'TypeScript', 'jsx' => 'JavaScript (JSX)', 'tsx' => 'TypeScript (TSX)',
        'py' => 'Python', 'rb' => 'Ruby', 'go' => 'Go', 'rs' => 'Rust', 'java' => 'Java', 'kt' => 'Kotlin',
        'c' => 'C', 'h' => 'C header', 'cpp' => 'C++', 'cs' => 'C#', 'swift' => 'Swift', 'sql' => 'SQL',
        'html' => 'HTML', 'css' => 'CSS', 'scss' => 'SCSS', 'json' => 'JSON', 'yml' => 'YAML', 'yaml' => 'YAML',
        'xml' => 'XML', 'md' => 'Markdown', 'sh' => 'Shell', 'txt' => 'Plain text',
    ];

    public function index(): void
    {
        $user = BuiltInApps::requireAccess('codebase');
        $pdo = app_db();

        $listStatement = $pdo->prepare(
            'SELECT id, name, language, updated_at FROM code_files WHERE user_id = :user_id ORDER BY name, id LIMIT 200'
        );
        $listStatement->execute(['user_id' => $user['id']]);
        $files = $listStatement->fetchAll(PDO::FETCH_ASSOC);

        $file = null;
        $fileId = filter_var($_GET['file'] ?? null, FILTER_VALIDATE_INT);
        if (($fileId === false || $fileId === null) && $files !== []) {
            $fileId = (int) $files[0]['id'];
        }
        if (is_int($fileId) && $fileId > 0) {
            $file = $this->find($pdo, (int) $user['id'], $fileId);
            if ($file === null) {
                set_flash_message('error', 'That file is not available.');
                redirect('/codebase');
            }
        }

        render('codebase/index', [
            'title' => 'Codebase',
            'user' => $user,
            'files' => $files,
            'file' => $file,
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function save(): void
    {
        $user = BuiltInApps::requireAccess('codebase');
        $this->requireCsrf();
        $pdo = app_db();
        $now = gmdate('Y-m-d H:i:s');

        $name = trim((string) ($_POST['name'] ?? ''));
        $content = str_replace("\r\n", "\n", (string) ($_POST['content'] ?? ''));
        $fileId = filter_var($_POST['file_id'] ?? '', FILTER_VALIDATE_INT);

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._\- ]{0,119}$/', $name)) {
            set_flash_message('error', 'Use a file name with letters, numbers, dots, dashes or underscores (up to 120 characters).');
            redirect($fileId !== false && $fileId > 0 ? '/codebase?file=' . $fileId : '/codebase');
        }
        if (strlen($content) > self::MAX_BYTES) {
            set_flash_message('error', 'Files can be up to 500 KB.');
            redirect($fileId !== false && $fileId > 0 ? '/codebase?file=' . $fileId : '/codebase');
        }
        $language = self::languageFor($name);

        if ($fileId === false || $fileId < 1) {
            $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
            $statement = $pdo->prepare(
                'INSERT INTO code_files (user_id, name, language, content, created_at, updated_at)
                 VALUES (:user_id, :name, :language, :content, :created_at, :updated_at)' . ($isPgsql ? ' RETURNING id' : '')
            );
            $statement->execute([
                'user_id' => $user['id'],
                'name' => $name,
                'language' => $language,
                'content' => $content,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $newId = (int) ($isPgsql ? $statement->fetchColumn() : $pdo->lastInsertId());
            set_flash_message('success', $name . ' created.');
            redirect('/codebase?file=' . $newId);
        }

        if ($this->find($pdo, (int) $user['id'], $fileId) === null) {
            set_flash_message('error', 'That file is not available.');
            redirect('/codebase');
        }

        $pdo->prepare(
            'UPDATE code_files SET name = :name, language = :language, content = :content, updated_at = :updated_at
             WHERE id = :id AND user_id = :user_id'
        )->execute([
            'name' => $name,
            'language' => $language,
            'content' => $content,
            'updated_at' => $now,
            'id' => $fileId,
            'user_id' => $user['id'],
        ]);

        set_flash_message('success', $name . ' saved.');
        redirect('/codebase?file=' . $fileId);
    }

    public function delete(): void
    {
        $user = BuiltInApps::requireAccess('codebase');
        $this->requireCsrf();
        $fileId = filter_var($_POST['file_id'] ?? '', FILTER_VALIDATE_INT);
        if ($fileId !== false && $fileId > 0) {
            app_db()->prepare('DELETE FROM code_files WHERE id = :id AND user_id = :user_id')->execute([
                'id' => $fileId,
                'user_id' => $user['id'],
            ]);
        }
        set_flash_message('success', 'File deleted.');
        redirect('/codebase');
    }

    public static function languageFor(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return self::LANGUAGES[$extension] ?? 'Plain text';
    }

    /** @return array{id: int, name: string, language: string, content: string, updated_at: string}|null */
    private function find(PDO $pdo, int $userId, int $fileId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT id, name, language, content, updated_at FROM code_files WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute(['id' => $fileId, 'user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'language' => (string) $row['language'],
            'content' => (string) $row['content'],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    private function requireCsrf(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);
            exit;
        }
    }
}
