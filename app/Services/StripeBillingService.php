<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Env;
use Closure;
use PDO;
use PDOException;
use RuntimeException;

final class StripeBillingService
{
    private readonly StripeApiClient $stripe;

    /**
     * @param null|Closure(string, string, array<string, mixed>, string, ?string): array{status: int, body: string} $requester
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly IntegrationConfigService $integrations,
        ?Closure $requester = null
    ) {
        $this->stripe = new StripeApiClient($integrations, $requester);
    }

    public function createCheckoutSession(int $userId, string $email, string $name): string
    {
        $plan = (new MembershipPlanService($this->pdo))->get('pro');
        if (!$plan['enabled']) {
            throw new RuntimeException('The Pro plan is not currently available.');
        }

        $membership = $this->membership($userId);
        if ($membership['plan_key'] === 'pro' && in_array($membership['status'], ['active', 'trialing', 'past_due'], true)) {
            throw new RuntimeException('Your account already has an active Pro membership. Manage it from Billing.');
        }

        $customerId = $membership['stripe_customer_id'];
        if (!is_string($customerId) || $customerId === '' || !str_starts_with($customerId, 'cus_')) {
            $customer = $this->stripe->request('POST', 'customers', [
                'email' => $email,
                'name' => $name,
                'metadata' => ['garwin_user_id' => (string) $userId],
            ], 'garwin-customer-' . $userId);
            $customerId = $customer['id'] ?? null;
            if (!is_string($customerId) || !str_starts_with($customerId, 'cus_')) {
                throw new RuntimeException('Stripe did not return a valid billing customer.');
            }
            $saveCustomer = $this->pdo->prepare(
                'UPDATE memberships SET stripe_customer_id = :customer_id, updated_at = :updated_at
                 WHERE user_id = :user_id'
            );
            $saveCustomer->execute([
                'customer_id' => $customerId,
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'user_id' => $userId,
            ]);
        }

        $baseUrl = $this->applicationUrl();
        $session = $this->stripe->request('POST', 'checkout/sessions', [
            'mode' => 'subscription',
            'customer' => $customerId,
            'client_reference_id' => (string) $userId,
            'success_url' => $baseUrl . '/billing?checkout=success',
            'cancel_url' => $baseUrl . '/billing?checkout=cancel',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $plan['price_cents'],
                    'recurring' => ['interval' => $plan['billing_interval']],
                    'product_data' => ['name' => 'GarwinAI Pro membership'],
                ],
            ]],
            'metadata' => [
                'garwin_user_id' => (string) $userId,
                'plan_key' => 'pro',
            ],
            'subscription_data' => [
                'metadata' => [
                    'garwin_user_id' => (string) $userId,
                    'plan_key' => 'pro',
                ],
            ],
        ], 'garwin-checkout-' . bin2hex(random_bytes(16)));
        $checkoutUrl = $session['url'] ?? null;
        if (!is_string($checkoutUrl) || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https'
            || parse_url($checkoutUrl, PHP_URL_HOST) !== 'checkout.stripe.com') {
            throw new RuntimeException('Stripe did not return a valid checkout link.');
        }

        return $checkoutUrl;
    }

    public function createPortalSession(int $userId): string
    {
        $membership = $this->membership($userId);
        $customerId = $membership['stripe_customer_id'];
        if (!is_string($customerId) || $customerId === '' || !str_starts_with($customerId, 'cus_')) {
            throw new RuntimeException('No Stripe billing account is linked to this membership.');
        }

        $session = $this->stripe->request('POST', 'billing_portal/sessions', [
            'customer' => $customerId,
            'return_url' => $this->applicationUrl() . '/billing',
        ]);
        $portalUrl = $session['url'] ?? null;
        if (!is_string($portalUrl) || parse_url($portalUrl, PHP_URL_SCHEME) !== 'https'
            || parse_url($portalUrl, PHP_URL_HOST) !== 'billing.stripe.com') {
            throw new RuntimeException('Stripe did not return a valid billing portal link.');
        }

        return $portalUrl;
    }

    public function createAppZoneCheckoutSession(
        int $userId,
        string $email,
        string $name,
        int $appId
    ): string {
        $item = $this->pdo->prepare(
            'SELECT name, price_cents FROM appzone_items WHERE id = :id AND is_published = 1'
        );
        $item->execute(['id' => $appId]);
        $app = $item->fetch(PDO::FETCH_ASSOC);
        if ($app === false || (int) $app['price_cents'] < 1) {
            throw new RuntimeException('That paid AppZone item is not available.');
        }

        $installed = $this->pdo->prepare(
            'SELECT 1 FROM appzone_installs WHERE app_id = :app_id AND user_id = :user_id LIMIT 1'
        );
        $installed->execute(['app_id' => $appId, 'user_id' => $userId]);
        if ($installed->fetchColumn() !== false) {
            throw new RuntimeException('This app is already in your workspace.');
        }

        $baseUrl = $this->applicationUrl();
        $pendingStatement = $this->pdo->prepare(
            'SELECT purchase_id, checkout_url, expires_at, created_at
             FROM appzone_checkout_sessions
             WHERE app_id = :app_id AND user_id = :user_id AND status = :status
             ORDER BY created_at DESC LIMIT 1'
        );
        $pendingStatement->execute(['app_id' => $appId, 'user_id' => $userId, 'status' => 'pending']);
        $pending = $pendingStatement->fetch(PDO::FETCH_ASSOC);
        if ($pending !== false) {
            $expiresAt = filter_var($pending['expires_at'], FILTER_VALIDATE_INT);
            if ($expiresAt !== false && $expiresAt > time()
                && is_string($pending['checkout_url'])
                && parse_url($pending['checkout_url'], PHP_URL_SCHEME) === 'https'
                && parse_url($pending['checkout_url'], PHP_URL_HOST) === 'checkout.stripe.com') {
                return $pending['checkout_url'];
            }
            $createdAt = strtotime((string) $pending['created_at']);
            if ($expiresAt === false && $createdAt !== false && $createdAt > time() - 120) {
                throw new RuntimeException('Your AppZone checkout is being prepared. Try again shortly.');
            }
            $this->pdo->prepare(
                'UPDATE appzone_checkout_sessions SET status = :failed_status WHERE purchase_id = :purchase_id'
            )->execute(['failed_status' => 'failed', 'purchase_id' => $pending['purchase_id']]);
        }

        $purchaseId = bin2hex(random_bytes(16));
        $this->pdo->prepare(
            'INSERT INTO appzone_checkout_sessions
             (purchase_id, user_id, app_id, amount_cents, status, created_at)
             VALUES (:purchase_id, :user_id, :app_id, :amount_cents, :status, :created_at)'
        )->execute([
            'purchase_id' => $purchaseId,
            'user_id' => $userId,
            'app_id' => $appId,
            'amount_cents' => (int) $app['price_cents'],
            'status' => 'pending',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        try {
            $session = $this->stripe->request('POST', 'checkout/sessions', [
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'customer_email' => $email,
                'client_reference_id' => (string) $userId,
                'success_url' => $baseUrl . '/appzone?checkout=success',
                'cancel_url' => $baseUrl . '/appzone?checkout=cancel',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => (int) $app['price_cents'],
                        'product_data' => ['name' => (string) $app['name']],
                    ],
                ]],
                'metadata' => ['appzone_purchase_id' => $purchaseId],
                'payment_intent_data' => [
                    'metadata' => ['appzone_purchase_id' => $purchaseId],
                ],
            ], 'garwin-appzone-' . $purchaseId);
        } catch (RuntimeException|\InvalidArgumentException $exception) {
            $this->pdo->prepare(
                'UPDATE appzone_checkout_sessions SET status = :failed_status WHERE purchase_id = :purchase_id'
            )->execute(['failed_status' => 'failed', 'purchase_id' => $purchaseId]);

            throw $exception;
        }
        $checkoutUrl = $session['url'] ?? null;
        $sessionId = $session['id'] ?? null;
        $expiresAt = filter_var($session['expires_at'] ?? null, FILTER_VALIDATE_INT);
        if (!is_string($checkoutUrl) || parse_url($checkoutUrl, PHP_URL_SCHEME) !== 'https'
            || parse_url($checkoutUrl, PHP_URL_HOST) !== 'checkout.stripe.com'
            || !is_string($sessionId) || !str_starts_with($sessionId, 'cs_')
            || $expiresAt === false || $expiresAt <= time()) {
            $this->pdo->prepare(
                'UPDATE appzone_checkout_sessions SET status = :failed_status WHERE purchase_id = :purchase_id'
            )->execute(['failed_status' => 'failed', 'purchase_id' => $purchaseId]);
            throw new RuntimeException('Stripe did not return a valid AppZone checkout session.');
        }

        $this->pdo->prepare(
            'UPDATE appzone_checkout_sessions
             SET stripe_checkout_session_id = :session_id, checkout_url = :checkout_url, expires_at = :expires_at
             WHERE purchase_id = :purchase_id'
        )->execute([
            'session_id' => $sessionId,
            'checkout_url' => $checkoutUrl,
            'expires_at' => $expiresAt,
            'purchase_id' => $purchaseId,
        ]);

        return $checkoutUrl;
    }

    public function processWebhook(string $payload, string $signature): bool
    {
        $credentials = $this->integrations->requiredCredentials('stripe');
        $this->verifySignature($payload, $signature, $credentials['webhook_secret']);

        try {
            $event = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('The Stripe event payload is invalid.');
        }
        if (!is_array($event) || !is_string($event['id'] ?? null)
            || !is_string($event['type'] ?? null) || !is_int($event['created'] ?? null)
            || !is_array($event['data']['object'] ?? null)) {
            throw new \InvalidArgumentException('The Stripe event payload is incomplete.');
        }

        $this->pdo->beginTransaction();
        try {
            $seen = $this->pdo->prepare(
                'SELECT 1 FROM stripe_webhook_events WHERE stripe_event_id = :event_id'
            );
            $seen->execute(['event_id' => $event['id']]);
            if ($seen->fetchColumn() !== false) {
                $this->pdo->commit();

                return false;
            }

            $insert = $this->pdo->prepare(
                'INSERT INTO stripe_webhook_events (stripe_event_id, event_type, event_created, received_at)
                 VALUES (:event_id, :event_type, :event_created, :received_at)'
            );
            $insert->execute([
                'event_id' => $event['id'],
                'event_type' => $event['type'],
                'event_created' => $event['created'],
                'received_at' => gmdate('Y-m-d H:i:s'),
            ]);

            if (in_array($event['type'], [
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
            ], true)) {
                $this->synchronizeSubscription(
                    $event['data']['object'],
                    $event['created'],
                    $event['type'] === 'customer.subscription.deleted'
                );
            } elseif (in_array($event['type'], [
                'checkout.session.completed',
                'checkout.session.async_payment_succeeded',
            ], true)) {
                $this->synchronizeAppZonePurchase($event['data']['object']);
            }

            $this->pdo->commit();

            return true;
        } catch (PDOException $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $seen = $this->pdo->prepare(
                'SELECT 1 FROM stripe_webhook_events WHERE stripe_event_id = :event_id'
            );
            $seen->execute(['event_id' => $event['id']]);
            if ($seen->fetchColumn() !== false) {
                return false;
            }

            throw $exception;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function synchronizeAppZonePurchase(array $session): void
    {
        if (($session['mode'] ?? null) !== 'payment' || ($session['payment_status'] ?? null) !== 'paid') {
            return;
        }

        $sessionId = $session['id'] ?? null;
        $metadata = $session['metadata'] ?? null;
        $purchaseId = is_array($metadata) ? ($metadata['appzone_purchase_id'] ?? null) : null;
        $amount = filter_var($session['amount_total'] ?? null, FILTER_VALIDATE_INT);
        $currency = $session['currency'] ?? null;
        if (!is_string($sessionId) || !str_starts_with($sessionId, 'cs_')
            || !is_string($purchaseId) || !preg_match('/\A[a-f0-9]{32}\z/', $purchaseId)
            || $amount === false || !is_string($currency)) {
            throw new \InvalidArgumentException('The AppZone checkout event is incomplete.');
        }

        $statement = $this->pdo->prepare(
            'SELECT user_id, app_id, amount_cents, stripe_checkout_session_id, status
             FROM appzone_checkout_sessions WHERE purchase_id = :purchase_id'
        );
        $statement->execute(['purchase_id' => $purchaseId]);
        $purchase = $statement->fetch(PDO::FETCH_ASSOC);
        if ($purchase === false) {
            throw new RuntimeException('The AppZone checkout session is not recognized.');
        }
        if ((int) $purchase['amount_cents'] !== $amount || strtolower($currency) !== 'usd') {
            throw new RuntimeException('The AppZone checkout amount does not match the pending purchase.');
        }
        if (is_string($purchase['stripe_checkout_session_id'])
            && $purchase['stripe_checkout_session_id'] !== ''
            && !hash_equals($purchase['stripe_checkout_session_id'], $sessionId)) {
            throw new RuntimeException('The AppZone checkout session does not match the pending purchase.');
        }
        if ($purchase['status'] === 'paid') {
            return;
        }
        if (!in_array($purchase['status'], ['pending', 'failed'], true)) {
            throw new RuntimeException('The AppZone checkout session is not available for fulfillment.');
        }

        $install = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
            ? $this->pdo->prepare(
                'INSERT IGNORE INTO appzone_installs (app_id, user_id, installed_at)
                 VALUES (:app_id, :user_id, :installed_at)'
            )
            : $this->pdo->prepare(
                'INSERT INTO appzone_installs (app_id, user_id, installed_at)
                 VALUES (:app_id, :user_id, :installed_at) ON CONFLICT (app_id, user_id) DO NOTHING'
            );
        $install->execute([
            'app_id' => $purchase['app_id'],
            'user_id' => $purchase['user_id'],
            'installed_at' => gmdate('Y-m-d H:i:s'),
        ]);
        if ($install->rowCount() > 0) {
            $this->pdo->prepare(
                'UPDATE appzone_items SET purchase_count = purchase_count + 1 WHERE id = :app_id'
            )->execute(['app_id' => $purchase['app_id']]);
        }

        $this->pdo->prepare(
            'UPDATE appzone_checkout_sessions
             SET stripe_checkout_session_id = :session_id, status = :status, paid_at = :paid_at
             WHERE purchase_id = :purchase_id AND status IN (:pending_status, :failed_status)'
        )->execute([
            'session_id' => $sessionId,
            'status' => 'paid',
            'paid_at' => gmdate('Y-m-d H:i:s'),
            'purchase_id' => $purchaseId,
            'pending_status' => 'pending',
            'failed_status' => 'failed',
        ]);
    }

    /** @return array{plan_key: string, status: string, stripe_customer_id: ?string, stripe_subscription_id: ?string, current_period_end: ?string, cancel_at_period_end: bool} */
    public function membership(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT plan_key, status, stripe_customer_id, stripe_subscription_id,
                    current_period_end, cancel_at_period_end
             FROM memberships WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('Membership details are not configured for this account.');
        }

        return [
            'plan_key' => (string) $row['plan_key'],
            'status' => (string) $row['status'],
            'stripe_customer_id' => is_string($row['stripe_customer_id']) && str_starts_with($row['stripe_customer_id'], 'cus_')
                ? $row['stripe_customer_id']
                : null,
            'stripe_subscription_id' => is_string($row['stripe_subscription_id']) && str_starts_with($row['stripe_subscription_id'], 'sub_')
                ? $row['stripe_subscription_id']
                : null,
            'current_period_end' => is_string($row['current_period_end']) ? $row['current_period_end'] : null,
            'cancel_at_period_end' => (bool) $row['cancel_at_period_end'],
        ];
    }

    private function synchronizeSubscription(array $subscription, int $eventCreated, bool $deleted): void
    {
        $subscriptionId = $subscription['id'] ?? null;
        $customerId = $subscription['customer'] ?? null;
        $metadata = $subscription['metadata'] ?? [];
        $userId = is_array($metadata) ? filter_var($metadata['garwin_user_id'] ?? null, FILTER_VALIDATE_INT) : false;
        if (!is_string($subscriptionId) || !str_starts_with($subscriptionId, 'sub_')
            || !is_string($customerId) || !str_starts_with($customerId, 'cus_')
            || $userId === false || $userId < 1) {
            return;
        }

        $current = $this->pdo->prepare(
            'SELECT stripe_customer_id, stripe_subscription_id, stripe_event_created
             FROM memberships WHERE user_id = :user_id'
        );
        $current->execute(['user_id' => $userId]);
        $membership = $current->fetch(PDO::FETCH_ASSOC);
        if ($membership === false || !is_string($membership['stripe_customer_id'])
            || !hash_equals($membership['stripe_customer_id'], $customerId)) {
            return;
        }
        if (is_string($membership['stripe_subscription_id'])
            && $membership['stripe_subscription_id'] !== ''
            && $membership['stripe_subscription_id'] !== $subscriptionId
            && $membership['stripe_event_created'] !== null
            && $eventCreated <= (int) $membership['stripe_event_created']) {
            return;
        }
        if ($membership['stripe_event_created'] !== null
            && $eventCreated < (int) $membership['stripe_event_created']) {
            return;
        }

        $status = $deleted ? 'canceled' : (string) ($subscription['status'] ?? 'incomplete');
        $periodEndTimestamp = filter_var($subscription['current_period_end'] ?? null, FILTER_VALIDATE_INT);
        $periodEnd = $periodEndTimestamp !== false && $periodEndTimestamp > 0
            ? gmdate('Y-m-d H:i:s', $periodEndTimestamp)
            : null;
        $cancelAtPeriodEnd = $this->normalizeBoolean($subscription['cancel_at_period_end'] ?? false);
        $isPro = !$deleted && (
            in_array($status, ['active', 'trialing'], true)
            || ($status === 'past_due' && $periodEndTimestamp !== false && $periodEndTimestamp > time())
        );
        $plan = (new MembershipPlanService($this->pdo))->get($isPro ? 'pro' : 'free');
        $statement = $this->pdo->prepare(
            'UPDATE memberships
             SET plan_key = :plan_key,
                 status = :status,
                 stripe_subscription_id = :subscription_id,
                 current_period_end = :period_end,
                 cancel_at_period_end = :cancel_at_period_end,
                 stripe_event_created = :event_created,
                 updated_at = :updated_at
             WHERE user_id = :user_id'
        );
        $statement->execute([
            'plan_key' => $plan['plan_key'],
            'status' => $status,
            'subscription_id' => $subscriptionId,
            'period_end' => $periodEnd,
            'cancel_at_period_end' => !$deleted && $cancelAtPeriodEnd ? 1 : 0,
            'event_created' => $eventCreated,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
        $this->pdo->prepare(
            'UPDATE storage_accounts SET quota_bytes = :quota_bytes, updated_at = :updated_at WHERE user_id = :user_id'
        )->execute([
            'quota_bytes' => $plan['storage_quota_bytes'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
        $this->pdo->prepare(
            'UPDATE ai_budgets SET budget_cents = :budget_cents, updated_at = :updated_at WHERE user_id = :user_id'
        )->execute([
            'budget_cents' => $plan['ai_budget_cents'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
    }

    private function verifySignature(string $payload, string $signature, string $secret): void
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && preg_match('/\A[a-f0-9]{64}\z/i', $value)) {
                $signatures[] = strtolower($value);
            }
        }
        if ($timestamp === null || abs(time() - $timestamp) > 300 || $signatures === []) {
            throw new \InvalidArgumentException('The Stripe webhook signature is invalid or expired.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return;
            }
        }

        throw new \InvalidArgumentException('The Stripe webhook signature is invalid or expired.');
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'y'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'n', ''], true)) {
                return false;
            }
        }

        return false;
    }

    private function applicationUrl(): string
    {
        $url = Env::get('APP_URL', '');
        $parsed = is_string($url) ? parse_url($url) : false;
        if (!is_array($parsed) || !in_array($parsed['scheme'] ?? '', ['https', 'http'], true)
            || !isset($parsed['host']) || isset($parsed['user']) || isset($parsed['pass'])
            || isset($parsed['query']) || isset($parsed['fragment'])) {
            throw new RuntimeException('Set APP_URL to the application base URL before using Stripe billing.');
        }

        return rtrim($url, '/');
    }
}
