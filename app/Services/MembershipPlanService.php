<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class MembershipPlanService
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return list<array{plan_key: string, display_name: string, price_cents: int, billing_interval: string, storage_quota_bytes: int, ai_budget_cents: int, team_seats: int, enabled: bool}> */
    public function all(): array
    {
        $rows = $this->pdo->query(
            'SELECT plan_key, display_name, price_cents, billing_interval, storage_quota_bytes,
                    ai_budget_cents, team_seats, enabled
             FROM membership_plans ORDER BY price_cents, plan_key'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'plan_key' => (string) $row['plan_key'],
            'display_name' => (string) $row['display_name'],
            'price_cents' => (int) $row['price_cents'],
            'billing_interval' => (string) $row['billing_interval'],
            'storage_quota_bytes' => (int) $row['storage_quota_bytes'],
            'ai_budget_cents' => (int) $row['ai_budget_cents'],
            'team_seats' => (int) $row['team_seats'],
            'enabled' => (bool) $row['enabled'],
        ], $rows);
    }

    /** @return array{plan_key: string, display_name: string, price_cents: int, billing_interval: string, storage_quota_bytes: int, ai_budget_cents: int, team_seats: int, enabled: bool} */
    public function get(string $planKey): array
    {
        foreach ($this->all() as $plan) {
            if ($plan['plan_key'] === $planKey) {
                return $plan;
            }
        }

        throw new RuntimeException('The membership plan is not configured.');
    }

    public function update(
        string $planKey,
        int $priceCents,
        int $storageGigabytes,
        int $aiBudgetCents,
        int $teamSeats,
        bool $enabled
    ): void {
        if (!in_array($planKey, ['free', 'pro'], true)
            || ($planKey === 'free' && ($priceCents !== 0 || !$enabled))
            || ($planKey === 'pro' && ($priceCents < 100 || $priceCents > 99999999))
            || $storageGigabytes < 1 || $storageGigabytes > 100000
            || $aiBudgetCents < 0 || $aiBudgetCents > 10000000
            || $teamSeats < 0 || $teamSeats > 10000) {
            throw new \InvalidArgumentException('Enter valid plan pricing and limits.');
        }

        $statement = $this->pdo->prepare(
            'UPDATE membership_plans
             SET price_cents = :price_cents,
                 storage_quota_bytes = :storage_quota_bytes,
                 ai_budget_cents = :ai_budget_cents,
                 team_seats = :team_seats,
                 enabled = :enabled,
                 updated_at = :updated_at
             WHERE plan_key = :plan_key'
        );
        $statement->execute([
            'price_cents' => $priceCents,
            'storage_quota_bytes' => $storageGigabytes * 1073741824,
            'ai_budget_cents' => $aiBudgetCents,
            'team_seats' => $teamSeats,
            'enabled' => $enabled ? 1 : 0,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'plan_key' => $planKey,
        ]);
        if ($statement->rowCount() === 0) {
            $check = $this->pdo->prepare('SELECT 1 FROM membership_plans WHERE plan_key = :plan_key');
            $check->execute(['plan_key' => $planKey]);
            if ($check->fetchColumn() === false) {
                throw new RuntimeException('The membership plan is not configured.');
            }
        }

        $plan = $this->get($planKey);
        $this->pdo->prepare(
            'UPDATE storage_accounts SET quota_bytes = :quota_bytes, updated_at = :updated_at
             WHERE user_id IN (SELECT user_id FROM memberships WHERE plan_key = :plan_key)'
        )->execute([
            'quota_bytes' => $plan['storage_quota_bytes'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'plan_key' => $planKey,
        ]);
        $this->pdo->prepare(
            'UPDATE ai_budgets SET budget_cents = :budget_cents, updated_at = :updated_at
             WHERE user_id IN (SELECT user_id FROM memberships WHERE plan_key = :plan_key)'
        )->execute([
            'budget_cents' => $plan['ai_budget_cents'],
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'plan_key' => $planKey,
        ]);
    }
}
