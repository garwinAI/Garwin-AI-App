<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AiBudgetService;
use App\Services\ApiSecretCipher;
use App\Services\IntegrationConfigService;
use App\Services\OpenAiChatService;
use App\Support\Csrf;
use PDO;
use RuntimeException;

final class AssistantController
{
    private const HISTORY_LIMIT = 12;

    public function index(): void
    {
        $user = $this->requireWorkspaceUser();
        $pdo = app_db();
        $searchTerm = trim((string) ($_GET['search'] ?? ''));
        $conversationId = filter_var($_GET['conversation'] ?? null, FILTER_VALIDATE_INT);
        $conversation = null;
        $messages = [];

        $sql = 'SELECT id, title, updated_at
                FROM ai_conversations
                WHERE user_id = :user_id';
        $params = ['user_id' => $user['id']];
        if ($searchTerm !== '') {
            $sql .= ' AND title LIKE :search';
            $params['search'] = '%' . $searchTerm . '%';
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT 50';
        $conversationsStatement = $pdo->prepare($sql);
        $conversationsStatement->execute($params);
        $conversations = $conversationsStatement->fetchAll(PDO::FETCH_ASSOC);

        if ($conversationId !== false && $conversationId !== null) {
            $conversationStatement = $pdo->prepare(
                'SELECT id, title FROM ai_conversations WHERE id = :id AND user_id = :user_id'
            );
            $conversationStatement->execute(['id' => $conversationId, 'user_id' => $user['id']]);
            $conversation = $conversationStatement->fetch(PDO::FETCH_ASSOC);
            if ($conversation === false) {
                set_flash_message('error', 'That conversation is not available.');
                redirect('/assistant');
            }
            $messageStatement = $pdo->prepare(
                'SELECT role, content, created_at
                 FROM ai_messages
                 WHERE conversation_id = :conversation_id
                 ORDER BY id'
            );
            $messageStatement->execute(['conversation_id' => $conversationId]);
            $messages = $messageStatement->fetchAll(PDO::FETCH_ASSOC);
        }

        $budgetStatus = null;
        try {
            $budgetStatus = (new AiBudgetService($pdo))->status($user['id']);
        } catch (RuntimeException) {
            // The AI budget row is initialized for all registered accounts.
        }

        render('assistant/index', [
            'title' => 'AI Assistant',
            'user' => $user,
            'conversations' => $conversations,
            'conversation' => $conversation,
            'messages' => $messages,
            'searchTerm' => $searchTerm,
            'budgetStatus' => $budgetStatus,
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function rename(): void
    {
        $user = $this->requireWorkspaceUser();
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            http_response_code(419);
            render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);
            return;
        }

        $conversationId = filter_var($_POST['conversation_id'] ?? '', FILTER_VALIDATE_INT);
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($conversationId === false || $conversationId < 1 || $title === '' || mb_strlen($title) > 80) {
            set_flash_message('error', 'Choose a valid conversation title and try again.');
            redirect('/assistant');
        }

        $pdo = app_db();
        $statement = $pdo->prepare(
            'UPDATE ai_conversations
             SET title = :title, updated_at = :updated_at
             WHERE id = :id AND user_id = :user_id'
        );
        $statement->execute([
            'title' => mb_substr($title, 0, 80),
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $conversationId,
            'user_id' => $user['id'],
        ]);

        redirect('/assistant?conversation=' . $conversationId);
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

        $conversationId = filter_var($_POST['conversation_id'] ?? '', FILTER_VALIDATE_INT);
        if ($conversationId === false || $conversationId < 1) {
            set_flash_message('error', 'Choose a conversation to remove.');
            redirect('/assistant');
        }

        $pdo = app_db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM ai_messages WHERE conversation_id = :conversation_id')->execute([
                'conversation_id' => $conversationId,
            ]);
            $pdo->prepare('DELETE FROM ai_conversations WHERE id = :id AND user_id = :user_id')->execute([
                'id' => $conversationId,
                'user_id' => $user['id'],
            ]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        redirect('/assistant');
    }

    public function send(): void
    {
        $user = $this->requireWorkspaceUser();
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            if ($this->wantsJson()) {
                $this->json(['error' => 'Your session expired. Refresh the page and try again.'], 419);
            }
            http_response_code(419);
            render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);
            return;
        }

        $promptInput = $_POST['message'] ?? '';
        $conversationInput = $_POST['conversation_id'] ?? '';
        if (!is_string($promptInput) || !is_string($conversationInput)) {
            $this->invalidPrompt();
        }

        $prompt = trim($promptInput);
        if ($prompt === '' || mb_strlen($prompt) > 4000 || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $prompt)) {
            $this->invalidPrompt();
        }

        $pdo = app_db();
        $conversationId = null;
        $history = [];
        if ($conversationInput !== '') {
            $conversationId = filter_var($conversationInput, FILTER_VALIDATE_INT);
            if ($conversationId === false || $conversationId < 1) {
                $this->invalidPrompt();
            }
            $ownerStatement = $pdo->prepare(
                'SELECT id FROM ai_conversations WHERE id = :id AND user_id = :user_id'
            );
            $ownerStatement->execute(['id' => $conversationId, 'user_id' => $user['id']]);
            if ($ownerStatement->fetchColumn() === false) {
                if ($this->wantsJson()) {
                    $this->json(['error' => 'That conversation is not available.'], 404);
                }
                set_flash_message('error', 'That conversation is not available.');
                redirect('/assistant');
            }

            $historyStatement = $pdo->prepare(
                'SELECT role, content
                 FROM ai_messages
                 WHERE conversation_id = :conversation_id
                 ORDER BY id DESC
                 LIMIT ' . self::HISTORY_LIMIT
            );
            $historyStatement->execute(['conversation_id' => $conversationId]);
            $history = array_reverse($historyStatement->fetchAll(PDO::FETCH_ASSOC));
        }

        $budgetService = new AiBudgetService($pdo);
        $service = new OpenAiChatService(
            new IntegrationConfigService($pdo, new ApiSecretCipher()),
            budgetService: $budgetService,
            userId: $user['id']
        );
        try {
            $reply = $service->reply($history, $prompt);
        } catch (\InvalidArgumentException|RuntimeException $exception) {
            if ($this->wantsJson()) {
                $this->json(['error' => $exception->getMessage()], 422);
            }
            set_flash_message('error', $exception->getMessage());
            redirect($conversationId === null ? '/assistant' : '/assistant?conversation=' . $conversationId);
        }

        $now = gmdate('Y-m-d H:i:s');
        $pdo->beginTransaction();
        try {
            if ($conversationId === null) {
                $title = mb_substr($prompt, 0, 80);
                $createSql = 'INSERT INTO ai_conversations (user_id, title, created_at, updated_at)
                     VALUES (:user_id, :title, :created_at, :updated_at)';
                if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
                    $createSql .= ' RETURNING id';
                }
                $createConversation = $pdo->prepare($createSql);
                $createConversation->execute([
                    'user_id' => $user['id'],
                    'title' => $title,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $conversationId = (int) ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
                    ? $createConversation->fetchColumn()
                    : $pdo->lastInsertId());
            }

            $insertMessage = $pdo->prepare(
                'INSERT INTO ai_messages (conversation_id, role, content, created_at)
                 VALUES (:conversation_id, :role, :content, :created_at)'
            );
            foreach ([['user', $prompt], ['assistant', $reply]] as [$role, $content]) {
                $insertMessage->execute([
                    'conversation_id' => $conversationId,
                    'role' => $role,
                    'content' => $content,
                    'created_at' => $now,
                ]);
            }
            $updateConversation = $pdo->prepare(
                'UPDATE ai_conversations SET updated_at = :updated_at WHERE id = :id AND user_id = :user_id'
            );
            $updateConversation->execute([
                'updated_at' => $now,
                'id' => $conversationId,
                'user_id' => $user['id'],
            ]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        if ($this->wantsJson()) {
            $this->json([
                'conversation_id' => $conversationId,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                    ['role' => 'assistant', 'content' => $reply],
                ],
            ]);
        }

        redirect('/assistant?conversation=' . $conversationId);
    }

    /** JSON history for the chat window: GET /assistant/messages?conversation=ID */
    public function messages(): void
    {
        $user = $this->requireWorkspaceUser(json: true);
        $conversationId = filter_var($_GET['conversation'] ?? '', FILTER_VALIDATE_INT);
        if ($conversationId === false || $conversationId < 1) {
            $this->json(['error' => 'Choose a conversation.'], 422);
        }

        $pdo = app_db();
        $conversationStatement = $pdo->prepare(
            'SELECT id, title FROM ai_conversations WHERE id = :id AND user_id = :user_id'
        );
        $conversationStatement->execute(['id' => $conversationId, 'user_id' => $user['id']]);
        $conversation = $conversationStatement->fetch(PDO::FETCH_ASSOC);
        if ($conversation === false) {
            $this->json(['error' => 'That conversation is not available.'], 404);
        }

        $messageStatement = $pdo->prepare(
            'SELECT role, content FROM ai_messages WHERE conversation_id = :conversation_id ORDER BY id'
        );
        $messageStatement->execute(['conversation_id' => $conversationId]);

        $this->json([
            'conversation_id' => (int) $conversation['id'],
            'title' => $conversation['title'],
            'messages' => $messageStatement->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    private function invalidPrompt(): never
    {
        if ($this->wantsJson()) {
            $this->json(['error' => 'Enter a message of up to 4,000 characters and try again.'], 422);
        }
        set_flash_message('error', 'Enter a message of up to 4,000 characters and try again.');
        redirect('/assistant');
    }

    private function requireWorkspaceUser(bool $json = false): array
    {
        $json = $json || $this->wantsJson();
        $user = current_user();
        if ($user === null) {
            if ($json) {
                $this->json(['error' => 'Please log in again.'], 401);
            }
            redirect('/login');
        }
        if ($user['is_admin']) {
            if ($json) {
                $this->json(['error' => 'The AI Assistant is available in user workspaces.'], 403);
            }
            redirect('/admin');
        }

        return $user;
    }

    private function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
