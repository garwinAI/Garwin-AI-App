<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Csrf;
use PDO;
use PDOException;

final class WordbaseController
{
    public function index(): void
    {
        $user = $this->requireWorkspaceUser();
        $pdo = app_db();

        $allDocuments = [];
        $document = null;

        try {
            $documents = $pdo->prepare(
                'SELECT id, title, updated_at
                 FROM wordbase_documents
                 WHERE user_id = :user_id
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 50'
            );
            $documents->execute(['user_id' => $user['id']]);
            $allDocuments = $documents->fetchAll(PDO::FETCH_ASSOC);

            $documentId = filter_var($_GET['document'] ?? null, FILTER_VALIDATE_INT);
            if ($documentId !== false && $documentId !== null && $documentId > 0) {
                $selected = $pdo->prepare(
                    'SELECT id, title, content, updated_at
                     FROM wordbase_documents
                     WHERE id = :id AND user_id = :user_id'
                );
                $selected->execute(['id' => $documentId, 'user_id' => $user['id']]);
                $document = $selected->fetch(PDO::FETCH_ASSOC);
            }

            if ($document === false || $document === null) {
                if ($allDocuments !== []) {
                    $selected = $pdo->prepare(
                        'SELECT id, title, content, updated_at
                         FROM wordbase_documents
                         WHERE id = :id AND user_id = :user_id'
                    );
                    $selected->execute(['id' => (int) $allDocuments[0]['id'], 'user_id' => $user['id']]);
                    $document = $selected->fetch(PDO::FETCH_ASSOC);
                }
            }
        } catch (PDOException) {
            $allDocuments = [];
            $document = null;
        }

        render('wordbase/index', [
            'title' => 'Wordbase',
            'user' => $user,
            'documents' => $allDocuments,
            'document' => $document,
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function save(): void
    {
        $user = $this->requireWorkspaceUser();
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            http_response_code(419);
            render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);
            return;
        }

        $pdo = app_db();
        try {
            $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
            $title = trim((string) ($_POST['title'] ?? ''));
            $content = (string) ($_POST['content'] ?? '');
            if ($title === '') {
                $title = 'Untitled document';
            }
            if (mb_strlen($title) > 255) {
                $title = mb_substr($title, 0, 255);
            }

            $now = gmdate('Y-m-d H:i:s');
            if ($documentId !== false && $documentId !== null && $documentId > 0) {
                $statement = $pdo->prepare(
                    'UPDATE wordbase_documents
                     SET title = :title, content = :content, updated_at = :updated_at
                     WHERE id = :id AND user_id = :user_id'
                );
                $statement->execute([
                    'title' => $title,
                    'content' => $content,
                    'updated_at' => $now,
                    'id' => $documentId,
                    'user_id' => $user['id'],
                ]);
                if ($statement->rowCount() === 0) {
                    set_flash_message('error', 'That document is not available.');
                    redirect('/wordbase');
                }
                redirect('/wordbase?document=' . $documentId);
            }

            $insert = $pdo->prepare(
                'INSERT INTO wordbase_documents (user_id, title, content, created_at, updated_at)
                 VALUES (:user_id, :title, :content, :created_at, :updated_at)'
            );
            $insert->execute([
                'user_id' => $user['id'],
                'title' => $title,
                'content' => $content,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $newId = (int) $pdo->lastInsertId();

            redirect('/wordbase?document=' . $newId);
        } catch (PDOException) {
            set_flash_message('error', 'Wordbase is not enabled for this installation yet. Run the optional migration and reload the page.');
            redirect('/wordbase');
        }
    }

    public function delete(): void
    {
        $user = $this->requireWorkspaceUser();
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            http_response_code(419);
            render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);
            return;
        }

        $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
        if ($documentId === false || $documentId === null || $documentId < 1) {
            set_flash_message('error', 'Choose a document to remove.');
            redirect('/wordbase');
        }

        $pdo = app_db();
        try {
            $statement = $pdo->prepare('DELETE FROM wordbase_documents WHERE id = :id AND user_id = :user_id');
            $statement->execute(['id' => $documentId, 'user_id' => $user['id']]);
            set_flash_message('success', 'Document removed.');
        } catch (PDOException) {
            set_flash_message('error', 'Wordbase is not enabled for this installation yet.');
        }
        redirect('/wordbase');
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
}
