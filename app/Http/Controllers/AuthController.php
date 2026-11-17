<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AuthenticationService;
use App\Services\RegistrationService;
use App\Services\StorageProvisioner;
use App\Services\UserIdGenerator;
use App\Support\Csrf;
use App\Support\Env;
use PDOException;
use RuntimeException;

final class AuthController
{
    public function showRegister(): void
    {
        $user = current_user();
        if ($user !== null) {
            redirect($user['is_admin'] ? '/admin' : '/dashboard');
        }

        render('auth/register', ['title' => 'Create your account']);
    }

    public function register(): void
    {
        if (!$this->validateCsrf()) {
            return;
        }

        $input = [
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
        ];
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];

        if ($input['first_name'] === '' || mb_strlen($input['first_name']) > 100) {
            $errors['first_name'] = 'Enter a first name of 1–100 characters.';
        }
        if ($input['last_name'] === '' || mb_strlen($input['last_name']) > 100) {
            $errors['last_name'] = 'Enter a last name of 1–100 characters.';
        }
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL) || strlen($input['email']) > 255) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($input['email'] === strtolower((string) Env::get('GARWIN_ADMIN_EMAIL', 'support@garwinai.com'))) {
            $errors['email'] = 'This email is reserved for the Super Admin account.';
        }
        if (strlen($password) < 12 || strlen($password) > 1024) {
            $errors['password'] = 'Use a password between 12 and 1024 characters.';
        }
        if (!hash_equals($password, (string) ($_POST['password_confirmation'] ?? ''))) {
            $errors['password_confirmation'] = 'The passwords do not match.';
        }
        if (($_POST['terms'] ?? '') !== 'accepted') {
            $errors['terms'] = 'Accept the terms to create an account.';
        }

        if ($errors !== []) {
            render('auth/register', ['title' => 'Create your account', 'errors' => $errors, 'old' => $input], 422);

            return;
        }

        $pdo = app_db();
        $registration = new RegistrationService(
            $pdo,
            new UserIdGenerator($pdo),
            new StorageProvisioner(dirname(__DIR__, 3))
        );

        try {
            $user = $registration->register(
                $input['email'],
                $password,
                $input['first_name'],
                $input['last_name'],
                true
            );
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === 'An account with this email already exists.') {
                render('auth/register', [
                    'title' => 'Create your account',
                    'errors' => ['email' => $exception->getMessage()],
                    'old' => $input,
                ], 422);

                return;
            }

            throw $exception;
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000' || (string) $exception->getCode() === '19') {
                render('auth/register', [
                    'title' => 'Create your account',
                    'errors' => ['email' => 'An account with this email already exists.'],
                    'old' => $input,
                ], 422);

                return;
            }

            throw $exception;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        unset($_SESSION['_csrf_token']);
        redirect('/dashboard');
    }

    public function showLogin(): void
    {
        $user = current_user();
        if ($user !== null) {
            redirect($user['is_admin'] ? '/admin' : '/dashboard');
        }

        render('auth/login', ['title' => 'Sign in']);
    }

    public function login(): void
    {
        if (!$this->validateCsrf()) {
            return;
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($password === '' || strlen($password) > 1024) {
            $errors['password'] = 'Enter your password.';
        }

        if ($errors !== []) {
            render('auth/login', ['title' => 'Sign in', 'errors' => $errors, 'old' => ['email' => $email]], 422);

            return;
        }

        $user = (new AuthenticationService(app_db()))->attempt(
            $email,
            $password,
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );

        if ($user === null) {
            render('auth/login', [
                'title' => 'Sign in',
                'errors' => ['form' => 'Email or password is incorrect, or sign-in is temporarily limited.'],
                'old' => ['email' => $email],
            ], 422);

            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        unset($_SESSION['_launch_checklist_dismissed']);
        unset($_SESSION['_csrf_token']);
        redirect($user['is_admin'] ? '/admin' : '/dashboard');
    }

    public function logout(): void
    {
        if (!$this->validateCsrf()) {
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        redirect('/');
    }

    private function validateCsrf(): bool
    {
        if (Csrf::isValid(isset($_POST['_token']) ? (string) $_POST['_token'] : null)) {
            return true;
        }

        http_response_code(419);
        render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);

        return false;
    }
}
