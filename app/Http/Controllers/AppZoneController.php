<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ApiSecretCipher;
use App\Services\IntegrationConfigService;
use App\Services\StripeBillingService;
use App\Support\Csrf;
use PDO;
use RuntimeException;

final class AppZoneController
{
    public function index(): void
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }

        $sortInput = $_GET['sort'] ?? 'top';
        $sort = is_string($sortInput) ? $sortInput : 'top';
        $apps = $this->apps($user['id'], $sort);
        $topItems = $this->apps($user['id'], 'top', 4);
        $mostViewed = $this->apps($user['id'], 'viewed', 4);
        $mostPurchased = $this->apps($user['id'], 'purchased', 4);

        render('appzone/index', [
            'title' => 'AppZone',
            'user' => $user,
            'apps' => $apps,
            'topItems' => $topItems,
            'mostViewed' => $mostViewed,
            'mostPurchased' => $mostPurchased,
            'sort' => $sort,
            'checkoutState' => is_string($_GET['checkout'] ?? null) ? $_GET['checkout'] : '',
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function open(): void
    {
        $user = $this->requireWorkspaceUser();
        $appId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($appId === false || $appId < 1) {
            set_flash_message('error', 'That AppZone item is not available.');
            redirect('/appzone');
        }

        $pdo = app_db();
        $item = $pdo->prepare('SELECT launch_url, price_cents FROM appzone_items WHERE id = :id AND is_published = 1');
        $item->execute(['id' => $appId]);
        $row = $item->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            set_flash_message('error', 'That app is unavailable or requires checkout.');
            redirect('/appzone');
        }

        $launchUrl = trim((string) ($row['launch_url'] ?? ''));
        if (!$this->isValidLaunchUrl($launchUrl)) {
            set_flash_message('error', 'That app is unavailable or requires checkout.');
            redirect('/appzone');
        }

        $price = (int) ($row['price_cents'] ?? 0);
        if ($price !== 0 && !$this->userHasInstalled($pdo, $appId, (int) $user['id'])) {
            set_flash_message('error', 'This AppZone item requires purchase before it can be opened.');
            redirect('/appzone');
        }

        $view = $pdo->prepare('UPDATE appzone_items SET view_count = view_count + 1 WHERE id = :id');
        $view->execute(['id' => $appId]);
        redirect((string) $row['launch_url']);
    }

    public function rate(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }

        $appId = filter_var($_POST['app_id'] ?? null, FILTER_VALIDATE_INT);
        $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
        if ($appId === false || $rating === false || $appId < 1 || $rating < 1 || $rating > 5) {
            set_flash_message('error', 'Choose a rating from one to five stars.');
            redirect('/appzone');
        }

        $pdo = app_db();
        $item = $pdo->prepare('SELECT 1 FROM appzone_items WHERE id = :id AND is_published = 1');
        $item->execute(['id' => $appId]);
        if ($item->fetchColumn() === false) {
            set_flash_message('error', 'That AppZone item is not available.');
            redirect('/appzone');
        }

        $now = gmdate('Y-m-d H:i:s');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            $statement = $pdo->prepare(
                'INSERT INTO appzone_ratings (app_id, user_id, rating, created_at, updated_at)
                 VALUES (:app_id, :user_id, :rating, :created_at, :updated_at)
                 ON CONFLICT (app_id, user_id) DO UPDATE SET rating = excluded.rating, updated_at = excluded.updated_at'
            );
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO appzone_ratings (app_id, user_id, rating, created_at, updated_at)
                 VALUES (:app_id, :user_id, :rating, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = VALUES(updated_at)'
            );
        }
        $statement->execute([
            'app_id' => $appId,
            'user_id' => $user['id'],
            'rating' => $rating,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        set_flash_message('success', 'Your rating was saved.');
        redirect('/appzone');
    }

    public function install(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }

        $appId = filter_var($_POST['app_id'] ?? null, FILTER_VALIDATE_INT);
        if ($appId === false || $appId < 1) {
            set_flash_message('error', 'That AppZone item is not available.');
            redirect('/appzone');
        }

        $pdo = app_db();
        $item = $pdo->prepare('SELECT price_cents FROM appzone_items WHERE id = :id AND is_published = 1');
        $item->execute(['id' => $appId]);
        $price = $item->fetchColumn();
        if ($price === false) {
            set_flash_message('error', 'That AppZone item is not available.');
            redirect('/appzone');
        }
        if ((int) $price !== 0) {
            set_flash_message('error', 'Paid items must be purchased through Stripe Checkout.');
            redirect('/appzone');
        }

        $now = gmdate('Y-m-d H:i:s');
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            $install = $pdo->prepare(
                'INSERT INTO appzone_installs (app_id, user_id, installed_at)
                 VALUES (:app_id, :user_id, :installed_at) ON CONFLICT (app_id, user_id) DO NOTHING'
            );
        } else {
            $install = $pdo->prepare(
                'INSERT IGNORE INTO appzone_installs (app_id, user_id, installed_at)
                 VALUES (:app_id, :user_id, :installed_at)'
            );
        }
        $install->execute(['app_id' => $appId, 'user_id' => $user['id'], 'installed_at' => $now]);
        set_flash_message('success', 'Free app added to your workspace.');
        redirect('/appzone');
    }

    public function purchase(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }

        $appId = filter_var($_POST['app_id'] ?? null, FILTER_VALIDATE_INT);
        if ($appId === false || $appId < 1) {
            set_flash_message('error', 'That AppZone item is not available.');
            redirect('/appzone');
        }

        try {
            $checkoutUrl = $this->billingService()->createAppZoneCheckoutSession(
                (int) $user['id'],
                (string) $user['email'],
                (string) ($user['first_name'] . ' ' . $user['last_name']),
                $appId
            );
            redirect($checkoutUrl);
        } catch (RuntimeException|\InvalidArgumentException $exception) {
            set_flash_message('error', $exception->getMessage());
            redirect('/appzone');
        }
    }

    public function admin(): void
    {
        $admin = $this->requireAdmin();
        if ($admin === null) {
            return;
        }
        $statement = app_db()->query(
            'SELECT i.id, i.name, i.description, i.launch_url, i.price_cents, i.is_published,
                    i.view_count, i.purchase_count,
                    (SELECT COUNT(*) FROM appzone_installs x WHERE x.app_id = i.id) AS install_count,
                    (SELECT COUNT(*) FROM appzone_ratings r WHERE r.app_id = i.id) AS rating_count
             FROM appzone_items i ORDER BY i.created_at DESC, i.id DESC'
        );
        render('admin/appzone', [
            'title' => 'AppZone catalog',
            'user' => $admin,
            'items' => $statement->fetchAll(PDO::FETCH_ASSOC),
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function create(): void
    {
        if ($this->requireAdmin() === null || !$this->validateCsrf()) {
            return;
        }

        $nameInput = $_POST['name'] ?? '';
        $descriptionInput = $_POST['description'] ?? '';
        $launchUrlInput = $_POST['launch_url'] ?? '';
        $priceValue = $_POST['price'] ?? '0';
        if (!is_string($nameInput) || !is_string($descriptionInput) || !is_string($launchUrlInput) || !is_string($priceValue)) {
            set_flash_message('error', 'Enter a name, valid web destination, and price from $0 to $99,999.99.');
            redirect('/admin/appzone');
        }
        $name = trim($nameInput);
        $description = trim($descriptionInput);
        $launchUrl = trim($launchUrlInput);
        $priceInput = trim($priceValue);
        $price = preg_match('/\A\d{1,5}(?:\.\d{1,2})?\z/', $priceInput) ? (int) round((float) $priceInput * 100) : false;
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || mb_strlen($description) > 2000
            || !$this->isValidLaunchUrl($launchUrl) || $price === false) {
            set_flash_message('error', 'Enter a name, valid web destination, and price from $0 to $99,999.99.');
            redirect('/admin/appzone');
        }

        $now = gmdate('Y-m-d H:i:s');
        $statement = app_db()->prepare(
            'INSERT INTO appzone_items (name, description, launch_url, price_cents, is_published, created_at, updated_at)
             VALUES (:name, :description, :launch_url, :price_cents, :is_published, :created_at, :updated_at)'
        );
        $statement->execute([
            'name' => $name,
            'description' => $description,
            'launch_url' => $launchUrl,
            'price_cents' => $price,
            'is_published' => isset($_POST['is_published']) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        set_flash_message('success', 'AppZone item created.');
        redirect('/admin/appzone');
    }

    public function togglePublished(): void
    {
        if ($this->requireAdmin() === null || !$this->validateCsrf()) {
            return;
        }

        $appId = filter_var($_POST['app_id'] ?? null, FILTER_VALIDATE_INT);
        if ($appId === false || $appId < 1) {
            set_flash_message('error', 'Choose a valid AppZone item.');
            redirect('/admin/appzone');
        }
        $statement = app_db()->prepare(
            'UPDATE appzone_items SET is_published = CASE WHEN is_published = 1 THEN 0 ELSE 1 END,
             updated_at = :updated_at WHERE id = :id'
        );
        $statement->execute(['updated_at' => gmdate('Y-m-d H:i:s'), 'id' => $appId]);
        if ($statement->rowCount() === 0) {
            set_flash_message('error', 'AppZone item was not found.');
        } else {
            set_flash_message('success', 'AppZone item visibility updated.');
        }
        redirect('/admin/appzone');
    }

    /** @return list<array<string, mixed>> */
    private function apps(int $userId, string $sort, int $limit = 0): array
    {
        $orders = [
            'top' => 'avg_rating DESC, rating_count DESC, name ASC',
            'viewed' => 'view_count DESC, name ASC',
            'purchased' => 'purchase_count DESC, name ASC',
            'price-low' => 'price_cents ASC, name ASC',
            'price-high' => 'price_cents DESC, name ASC',
            'name' => 'name ASC',
        ];
        $order = $orders[$sort] ?? $orders['top'];
        $limitSql = $limit > 0 ? ' LIMIT ' . min($limit, 20) : '';
        $statement = app_db()->prepare(
            'SELECT i.id, i.name, i.description, i.launch_url, i.price_cents, i.view_count, i.purchase_count,
                    (SELECT COUNT(*) FROM appzone_installs x WHERE x.app_id = i.id) AS install_count,
                    COALESCE((SELECT AVG(r.rating) FROM appzone_ratings r WHERE r.app_id = i.id), 0) AS avg_rating,
                    (SELECT COUNT(*) FROM appzone_ratings r WHERE r.app_id = i.id) AS rating_count,
                    COALESCE((SELECT r.rating FROM appzone_ratings r WHERE r.app_id = i.id AND r.user_id = :rating_user_id), 0) AS user_rating,
                    (SELECT COUNT(*) FROM appzone_installs x WHERE x.app_id = i.id AND x.user_id = :install_user_id) AS installed
             FROM appzone_items i WHERE i.is_published = 1 ORDER BY ' . $order . $limitSql
        );
        $statement->execute(['rating_user_id' => $userId, 'install_user_id' => $userId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function userHasInstalled(PDO $pdo, int $appId, int $userId): bool
    {
        $statement = $pdo->prepare('SELECT 1 FROM appzone_installs WHERE app_id = :app_id AND user_id = :user_id LIMIT 1');
        $statement->execute(['app_id' => $appId, 'user_id' => $userId]);

        return $statement->fetchColumn() !== false;
    }

    private function isValidLaunchUrl(string $launchUrl): bool
    {
        $trimmed = trim($launchUrl);
        if ($trimmed === '' || preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1 || str_contains($trimmed, ' ')) {
            return false;
        }

        if (str_starts_with($trimmed, '/')) {
            return !str_starts_with($trimmed, '//')
                && preg_match('/\A\/[^\s<>"\']*\z/', $trimmed) === 1
                && preg_match('/(?:^|\/)\.\.(?:\/|$)/', $trimmed) !== 1;
        }

        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = parse_url($trimmed, PHP_URL_HOST);

        return is_string($host) && $host !== '' && filter_var($trimmed, FILTER_VALIDATE_URL) !== false;
    }

    private function billingService(): StripeBillingService
    {
        $pdo = app_db();

        return new StripeBillingService($pdo, new IntegrationConfigService($pdo, new ApiSecretCipher()));
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

    private function requireAdmin(): ?array
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if (!$user['is_admin']) {
            render('error', [
                'title' => 'Access denied',
                'message' => 'This area is restricted to Super Admin accounts.',
            ], 403);
            return null;
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
}
