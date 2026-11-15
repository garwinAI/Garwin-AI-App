<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Env;
use Closure;
use RuntimeException;

final class StripeApiClient
{
    private readonly Closure $requester;

    /**
     * @param null|Closure(string, string, array<string, mixed>, string, ?string): array{status: int, body: string} $requester
     */
    public function __construct(
        private readonly IntegrationConfigService $integrations,
        ?Closure $requester = null
    ) {
        $this->requester = $requester ?? $this->sendRequest(...);
    }

    /** @param array<string, mixed> $parameters
     *  @return array<string, mixed>
     */
    public function request(
        string $method,
        string $path,
        array $parameters = [],
        ?string $idempotencyKey = null
    ): array {
        $credentials = $this->integrations->requiredCredentials('stripe');
        $status = array_values(array_filter(
            $this->integrations->statuses(),
            static fn (array $provider): bool => $provider['key'] === 'stripe'
        ))[0] ?? null;
        if (!is_array($status) || !$status['configured'] || $status['verified_at'] === null) {
            throw new RuntimeException('Stripe credentials must be saved and verified before billing is enabled.');
        }
        if (!preg_match('/\Ask_(?:test|live)_/', $credentials['secret_key'])) {
            throw new RuntimeException('A Stripe secret API key is required for billing.');
        }

        $result = ($this->requester)(
            $method,
            $path,
            $parameters,
            $credentials['secret_key'],
            $idempotencyKey
        );
        if ($result['status'] < 200 || $result['status'] >= 300) {
            throw new RuntimeException('Stripe could not complete the billing request. Check the Stripe account and credentials.');
        }

        try {
            $payload = json_decode($result['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Stripe returned an invalid billing response.');
        }
        if (!is_array($payload)) {
            throw new RuntimeException('Stripe returned an invalid billing response.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $parameters
     *  @return array{status: int, body: string}
     */
    private function sendRequest(
        string $method,
        string $path,
        array $parameters,
        string $secretKey,
        ?string $idempotencyKey
    ): array {
        $url = 'https://api.stripe.com/v1/' . ltrim($path, '/');
        if ($method === 'GET' && $parameters !== []) {
            $url .= '?' . http_build_query($parameters);
        }
        $request = curl_init($url);
        if ($request === false) {
            throw new RuntimeException('Unable to initialize the Stripe billing request.');
        }

        $headers = ['Authorization: Bearer ' . $secretKey];
        if ($idempotencyKey !== null) {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'GarwinAI-Billing/1.0',
        ];
        if ($method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = http_build_query($parameters);
        }
        if (!curl_setopt_array($request, $options)) {
            curl_close($request);
            throw new RuntimeException('Unable to configure the Stripe billing request.');
        }

        $body = curl_exec($request);
        $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        $errorNumber = curl_errno($request);
        curl_close($request);
        if (!is_string($body) || $errorNumber !== 0) {
            throw new RuntimeException('Could not connect to Stripe. Please try again later.');
        }

        return ['status' => $status, 'body' => $body];
    }
}
