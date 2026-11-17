<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\BuiltInApps;
use App\Support\Csrf;
use PDO;

final class SpreadsheetsController
{
    public const COLUMNS = 26;
    public const ROWS = 60;
    private const MAX_CELL_LENGTH = 1000;

    public function index(): void
    {
        $user = BuiltInApps::requireAccess('spreadsheets');
        $pdo = app_db();

        $listStatement = $pdo->prepare(
            'SELECT id, title, updated_at FROM spreadsheets WHERE user_id = :user_id ORDER BY updated_at DESC, id DESC LIMIT 100'
        );
        $listStatement->execute(['user_id' => $user['id']]);
        $sheets = $listStatement->fetchAll(PDO::FETCH_ASSOC);

        $sheet = null;
        $sheetId = filter_var($_GET['sheet'] ?? null, FILTER_VALIDATE_INT);
        if (($sheetId === false || $sheetId === null) && $sheets !== []) {
            $sheetId = (int) $sheets[0]['id'];
        }
        if (is_int($sheetId) && $sheetId > 0) {
            $sheet = $this->find($pdo, (int) $user['id'], $sheetId);
            if ($sheet === null) {
                set_flash_message('error', 'That spreadsheet is not available.');
                redirect('/spreadsheets');
            }
        }

        render('spreadsheets/index', [
            'title' => 'Spreadsheets',
            'user' => $user,
            'sheets' => $sheets,
            'sheet' => $sheet,
            'columns' => self::COLUMNS,
            'rows' => self::ROWS,
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function save(): void
    {
        $user = BuiltInApps::requireAccess('spreadsheets');
        $this->requireCsrf();
        $pdo = app_db();
        $now = gmdate('Y-m-d H:i:s');

        $sheetId = filter_var($_POST['sheet_id'] ?? '', FILTER_VALIDATE_INT);
        $title = trim((string) ($_POST['title'] ?? ''));
        $title = $title === '' ? 'Untitled spreadsheet' : mb_substr($title, 0, 120);

        if ($sheetId === false || $sheetId < 1) {
            $sql = 'INSERT INTO spreadsheets (user_id, title, cells_json, created_at, updated_at)
                    VALUES (:user_id, :title, :cells_json, :created_at, :updated_at)';
            $isPgsql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
            $statement = $pdo->prepare($sql . ($isPgsql ? ' RETURNING id' : ''));
            $statement->execute([
                'user_id' => $user['id'],
                'title' => $title,
                'cells_json' => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $newId = (int) ($isPgsql ? $statement->fetchColumn() : $pdo->lastInsertId());
            redirect('/spreadsheets?sheet=' . $newId);
        }

        if ($this->find($pdo, (int) $user['id'], $sheetId) === null) {
            set_flash_message('error', 'That spreadsheet is not available.');
            redirect('/spreadsheets');
        }

        $cells = $this->validatedCells((string) ($_POST['cells_json'] ?? '{}'));
        if ($cells === null) {
            set_flash_message('error', 'The spreadsheet could not be saved. Cells must be in A1–Z60 and up to 1,000 characters each.');
            redirect('/spreadsheets?sheet=' . $sheetId);
        }

        $statement = $pdo->prepare(
            'UPDATE spreadsheets SET title = :title, cells_json = :cells_json, updated_at = :updated_at
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'title' => $title,
            'cells_json' => json_encode((object) $cells, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
            'id' => $sheetId,
            'user_id' => $user['id'],
        ]);

        set_flash_message('success', 'Spreadsheet saved.');
        redirect('/spreadsheets?sheet=' . $sheetId);
    }

    public function delete(): void
    {
        $user = BuiltInApps::requireAccess('spreadsheets');
        $this->requireCsrf();
        $sheetId = filter_var($_POST['sheet_id'] ?? '', FILTER_VALIDATE_INT);
        if ($sheetId !== false && $sheetId > 0) {
            app_db()->prepare('DELETE FROM spreadsheets WHERE id = :id AND user_id = :user_id')->execute([
                'id' => $sheetId,
                'user_id' => $user['id'],
            ]);
        }
        set_flash_message('success', 'Spreadsheet deleted.');
        redirect('/spreadsheets');
    }

    /** @return array{id: int, title: string, cells: array<string, string>, updated_at: string}|null */
    private function find(PDO $pdo, int $userId, int $sheetId): ?array
    {
        $statement = $pdo->prepare(
            'SELECT id, title, cells_json, updated_at FROM spreadsheets WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute(['id' => $sheetId, 'user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $cells = json_decode((string) $row['cells_json'], true);

        return [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'cells' => is_array($cells) ? $cells : [],
            'updated_at' => (string) $row['updated_at'],
        ];
    }

    /** @return array<string, string>|null */
    private function validatedCells(string $json): ?array
    {
        if (strlen($json) > 2_000_000) {
            return null;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }

        $cells = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !is_string($value) || !preg_match('/^([A-Z])([1-9][0-9]?)$/', $key, $match)) {
                return null;
            }
            if ((int) $match[2] > self::ROWS || mb_strlen($value) > self::MAX_CELL_LENGTH) {
                return null;
            }
            if (trim($value) !== '') {
                $cells[$key] = $value;
            }
        }

        return $cells;
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
