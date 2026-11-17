<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use RuntimeException;

final class OpenAiChatService
{
    private readonly Closure $requester;

    /**
     * @param null|Closure(string, array<string, mixed>): array{status: int, body: string} $requester
     */
    public function __construct(
        private readonly IntegrationConfigService $integrationConfigs,
        ?Closure $requester = null,
        private readonly ?AiBudgetService $budgetService = null,
        private readonly ?int $userId = null
    ) {
        $this->requester = $requester ?? $this->sendRequest(...);
    }

    /** @param list<array{role: string, content: string}> $history */
    public function reply(array $history, string $prompt): string
    {
        $credentials = $this->integrationConfigs->requiredCredentials('openai');
        $messages = [
            ['role' => 'system', 'content' => 'You are GarwinAI Assistant. Be helpful, accurate, and concise.'],
        ];
        foreach ($history as $message) {
            if (!in_array($message['role'] ?? null, ['user', 'assistant'], true)
                || !is_string($message['content'] ?? null)) {
                throw new \InvalidArgumentException('Conversation history is invalid.');
            }
            $messages[] = ['role' => $message['role'], 'content' => $message['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $estimatedCost = 0;
        if ($this->budgetService !== null && $this->userId !== null) {
            $estimatedCost = $this->budgetService->estimateRequestCost($history, $prompt);
            if (!$this->budgetService->reserve($this->userId, $estimatedCost)) {
                throw new RuntimeException('You\'ve reached your monthly AI Assistant usage limit. Your available AI budget for this billing period has been used.');
            }
        }

        try {
            $result = ($this->requester)($credentials['api_key'], [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 1000,
            ]);
        } catch (RuntimeException $exception) {
            if ($this->budgetService !== null && $this->userId !== null && $estimatedCost > 0) {
                $this->budgetService->release($this->userId, $estimatedCost);
            }

            throw $exception;
        }

        if ($result['status'] < 200 || $result['status'] >= 300) {
            if ($this->budgetService !== null && $this->userId !== null && $estimatedCost > 0) {
                $this->budgetService->release($this->userId, $estimatedCost);
            }

            $message = match ($result['status']) {
                401, 403 => 'The AI provider rejected its configured API key. Contact the administrator.',
                429 => 'The AI provider is temporarily rate-limiting requests. Please try again shortly.',
                default => 'The AI provider could not complete your request. Please try again later.',
            };
            throw new RuntimeException($message);
        }

        try {
            $response = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            if ($this->budgetService !== null && $this->userId !== null && $estimatedCost > 0) {
                $this->budgetService->release($this->userId, $estimatedCost);
            }
            throw new RuntimeException('The AI provider returned an empty or invalid response.');
        }

        $choices = $response['choices'] ?? null;
        $content = is_array($choices) && isset($choices[0]['message']['content'])
            ? $choices[0]['message']['content']
            : null;
        if (!is_string($content) || trim($content) === '') {
            if ($this->budgetService !== null && $this->userId !== null && $estimatedCost > 0) {
                $this->budgetService->release($this->userId, $estimatedCost);
            }
            throw new RuntimeException('The AI provider returned an empty or invalid response.');
        }

        if ($this->budgetService !== null && $this->userId !== null && $estimatedCost > 0) {
            $usage = $response['usage'] ?? [];
            $promptTokens = max(0, (int) ($usage['prompt_tokens'] ?? 0));
            $completionTokens = max(0, (int) ($usage['completion_tokens'] ?? 0));
            $actualCost = max(1, (int) ceil(($promptTokens + $completionTokens) / 80));
            $this->budgetService->settle($this->userId, $estimatedCost, $actualCost);
        }

        return trim($content);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: int, body: string}
     */
    private function sendRequest(string $apiKey, array $payload): array
    {
        $request = curl_init('https://api.openai.com/v1/chat/completions');
        if ($request === false) {
            throw new RuntimeException('Unable to initialize the AI provider connection.');
        }

        curl_setopt_array($request, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'GarwinAI-Assistant/1.0',
        ]);
        $body = curl_exec($request);
        $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        $errorNumber = curl_errno($request);
        curl_close($request);

        if ($body === false || $errorNumber !== 0) {
            throw new RuntimeException('Could not connect to the AI provider. Please try again later.');
        }

        return ['status' => $status, 'body' => $body];
    }
}
