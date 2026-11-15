<?php

declare(strict_types=1);

use App\Support\Csrf;
use App\Support\Env;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function current_user(): ?array
{
    if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
        return null;
    }

    $statement = app_db()->prepare(
        "SELECT u.id, u.user_id, u.email, u.first_name, u.last_name,
                CASE WHEN a.role_key = 'super_admin' THEN 1 ELSE 0 END AS is_admin
         FROM users u
         LEFT JOIN admin_users a ON a.user_id = u.id
         WHERE u.id = :id LIMIT 1"
    );
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch(PDO::FETCH_ASSOC);

    if ($user === false) {
        return null;
    }

    $user['is_admin'] = (bool) $user['is_admin'];

    return $user;
}

function set_flash_message(string $type, string $message): void
{
    $_SESSION['_flash_message'] = ['type' => $type, 'message' => $message];
}

function take_flash_message(): ?array
{
    $message = $_SESSION['_flash_message'] ?? null;
    unset($_SESSION['_flash_message']);

    return is_array($message) ? $message : null;
}

function render(string $template, array $data = [], int $status = 200): void
{
    http_response_code($status);
    $viewPath = dirname(__DIR__) . '/resources/views/' . $template . '.php';
    if (!is_file($viewPath)) {
        throw new RuntimeException('Requested view does not exist.');
    }

    $data['user'] ??= current_user();
    $data['csrfToken'] = Csrf::token();
    $data['appName'] = Env::get('APP_NAME', 'GarwinAI');
    $data['supportEmail'] = Env::get('SUPPORT_EMAIL', 'support@garwinai.com');
    extract($data, EXTR_SKIP);

    ob_start();
    require $viewPath;
    $content = (string) ob_get_clean();
    require dirname(__DIR__) . '/resources/views/layout.php';
}
