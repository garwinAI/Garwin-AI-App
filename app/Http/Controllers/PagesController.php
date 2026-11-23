<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WorkspacePageService;
use App\Support\Csrf;

/** Workspace pages: Wordbase docs, spreadsheets and calendars from the Pages menu. */
final class PagesController
{
    public function show(): void
    {
        $user = $this->requireUser();
        $pageId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $page = is_int($pageId) ? $this->pages()->find((int) $user['id'], $pageId) : null;
        if ($page === null) {
            set_flash_message('error', 'That page could not be found.');
            redirect('/dashboard');
        }

        render('pages/show', [
            'title' => $page['title'],
            'user' => $user,
            'page' => $page,
            'pageType' => WorkspacePageService::TYPES[$page['page_type']] ?? WorkspacePageService::TYPES['doc'],
        ]);
    }

    public function create(): void
    {
        $user = $this->requireUser();
        $this->requireToken();
        $type = $_POST['type'] ?? '';
        $pageId = is_string($type) ? $this->pages()->create((int) $user['id'], $type) : null;
        if ($pageId === null) {
            set_flash_message('error', 'That page could not be created.');
            redirect('/dashboard');
        }

        redirect('/pages/view?id=' . $pageId);
    }

    public function duplicate(): void
    {
        $user = $this->requireUser();
        $this->requireToken();
        $pageId = filter_var($_POST['page_id'] ?? null, FILTER_VALIDATE_INT);
        $copyId = is_int($pageId) ? $this->pages()->duplicate((int) $user['id'], $pageId) : null;
        if ($copyId === null) {
            set_flash_message('error', 'That page could not be duplicated.');
            redirect('/dashboard');
        }

        redirect('/pages/view?id=' . $copyId);
    }

    public function delete(): void
    {
        $user = $this->requireUser();
        $this->requireToken();
        $pageId = filter_var($_POST['page_id'] ?? null, FILTER_VALIDATE_INT);
        $deleted = is_int($pageId) && $this->pages()->delete((int) $user['id'], $pageId);
        set_flash_message($deleted ? 'success' : 'error', $deleted ? 'Page deleted.' : 'That page could not be deleted.');

        $return = $_POST['return'] ?? '/dashboard';
        $returnId = is_string($return) && preg_match('/^\/pages\/view\?id=(\d+)$/', $return, $match) ? (int) $match[1] : null;
        $safeReturn = is_string($return) && str_starts_with($return, '/') && !str_starts_with($return, '//')
            && !preg_match('/[\r\n\\\\]/', $return) && $returnId !== $pageId ? $return : '/dashboard';
        redirect($safeReturn);
    }

    /** Autosave from the page editor; answers JSON. */
    public function save(): void
    {
        header('Content-Type: application/json');
        $user = current_user();
        if ($user === null || $user['is_admin']) {
            http_response_code(401);
            echo json_encode(['error' => 'Sign in again to save.']);
            return;
        }
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            http_response_code(419);
            echo json_encode(['error' => 'Session expired. Refresh the page; your last change was not saved.']);
            return;
        }
        $pageId = filter_var($_POST['page_id'] ?? null, FILTER_VALIDATE_INT);
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        if (!is_int($pageId) || !is_string($title) || !is_string($content)) {
            http_response_code(422);
            echo json_encode(['error' => 'That change could not be saved.']);
            return;
        }

        $error = $this->pages()->save((int) $user['id'], $pageId, $title, $content);
        if ($error !== null) {
            http_response_code(422);
            echo json_encode(['error' => $error]);
            return;
        }
        echo json_encode(['saved' => true]);
    }

    private function pages(): WorkspacePageService
    {
        return new WorkspacePageService(app_db());
    }

    private function requireUser(): array
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

    private function requireToken(): void
    {
        $token = $_POST['_token'] ?? null;
        if (!Csrf::isValid(is_string($token) ? $token : null)) {
            http_response_code(419);
            render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);
            exit;
        }
    }
}
