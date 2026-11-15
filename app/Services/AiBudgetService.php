<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class AiBudgetService
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /** @return array{budget_cents: int, spent_cents: int, available_cents: int, warning_level: float} */
    public function status(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT budget_cents, spent_cents FROM ai_budgets WHERE user_id = :user_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException('AI budgeting is unavailable for this account.');
        }

        $budgetCents = max(0, (int) $row['budget_cents']);
        $spentCents = max(0, (int) $row['spent_cents']);
        $warningLevel = $budgetCents > 0 ? ($spentCents / $budgetCents) : 0.0;

        return [
            'budget_cents' => $budgetCents,
            'spent_cents' => $spentCents,
            'available_cents' => max(0, $budgetCents - $spentCents),
            'warning_level' => $warningLevel,
        ];
    }

    public function estimateRequestCost(array $history, string $prompt): int
    {
        $historyText = '';
        foreach ($history as $message) {
            if (is_array($message) && is_string($message['content'] ?? null)) {
                $historyText .= $message['content'];
            }
        }

        $estimatedTokens = max(1, (int) ceil((mb_strlen($prompt) + mb_strlen($historyText)) / 24));

        return min(500, max(1, (int) ceil($estimatedTokens / 8)));
    }

    public function reserve(int $userId, int $amountCents): bool
    {
        if ($amountCents <= 0) {
            return true;
        }

        $status = $this->status($userId);
        if ($status['available_cents'] < $amountCents) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE ai_budgets
             SET spent_cents = spent_cents + :amount_cents,
                 updated_at = :updated_at
             WHERE user_id = :user_id'
        );
        $statement->execute([
            'amount_cents' => $amountCents,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);

        return true;
    }

    public function release(int $userId, int $amountCents): void
    {
        if ($amountCents <= 0) {
            return;
        }

        $statement = $this->pdo->prepare(
            'UPDATE ai_budgets
             SET spent_cents = CASE
                 WHEN spent_cents - :amount_cents < 0 THEN 0
                 ELSE spent_cents - :amount_cents
             END,
                 updated_at = :updated_at
             WHERE user_id = :user_id'
        );
        $statement->execute([
            'amount_cents' => $amountCents,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
    }

    public function settle(int $userId, int $estimatedCents, int $actualCents): void
    {
        $statement = $this->pdo->prepare(
            'SELECT spent_cents FROM ai_budgets WHERE user_id = :user_id LIMIT 1'
        );
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new RuntimeException('AI budgeting is unavailable for this account.');
        }

        $actualCents = max(0, $actualCents);
        $estimatedCents = max(0, $estimatedCents);
        $updatedSpent = max(0, (int) $row['spent_cents'] - $estimatedCents + $actualCents);
        $statement = $this->pdo->prepare(
            'UPDATE ai_budgets
             SET spent_cents = :spent_cents,
                 updated_at = :updated_at
             WHERE user_id = :user_id'
        );
        $statement->execute([
            'spent_cents' => $updatedSpent,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
    }
}
