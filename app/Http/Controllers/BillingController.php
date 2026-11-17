<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\IntegrationConfigService;
use App\Services\MembershipPlanService;
use App\Services\StripeBillingService;
use App\Support\Csrf;
use PDO;
use RuntimeException;

final class BillingController
{
    public function index(): void
    {
        $user = $this->requireWorkspaceUser();
        $pdo = app_db();
        $billing = $this->billingService($pdo);
        $membership = $billing->membership((int) $user['id']);
        $plans = (new MembershipPlanService($pdo))->all();

        render('billing/index', [
            'title' => 'Billing & membership',
            'user' => $user,
            'membership' => $membership,
            'plans' => $plans,
            'checkoutState' => is_string($_GET['checkout'] ?? null) ? $_GET['checkout'] : '',
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function checkout(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }

        try {
            $url = $this->billingService(app_db())->createCheckoutSession(
                (int) $user['id'],
                (string) $user['email'],
                (string) ($user['first_name'] . ' ' . $user['last_name'])
            );
            redirect($url);
        } catch (RuntimeException $exception) {
            set_flash_message('error', $exception->getMessage());
            redirect('/billing');
        }
    }

    public function portal(): void
    {
        $user = $this->requireWorkspaceUser();
        if (!$this->validateCsrf()) {
            return;
        }

        try {
            $url = $this->billingService(app_db())->createPortalSession((int) $user['id']);
            redirect($url);
        } catch (RuntimeException $exception) {
            set_flash_message('error', $exception->getMessage());
            redirect('/billing');
        }
    }

    public function webhook(): void
    {
        $payload = file_get_contents('php://input');
        if (!is_string($payload) || $payload === '') {
            http_response_code(400);
            echo 'Invalid Stripe event.';

            return;
        }

        try {
            $this->billingService(app_db())->processWebhook(
                $payload,
                (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '')
            );
        } catch (\InvalidArgumentException $exception) {
            http_response_code(400);
            echo 'Invalid Stripe event.';

            return;
        }

        http_response_code(200);
        header('Content-Type: application/json');
        echo '{"received":true}';
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

    private function billingService(PDO $pdo): StripeBillingService
    {
        return new StripeBillingService($pdo, new IntegrationConfigService($pdo, new \App\Services\ApiSecretCipher()));
    }
}
