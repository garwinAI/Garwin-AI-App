<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class IntegrationVerifier
{
    public function verify(string $provider, array $credentials): array
    {
        [$url, $headers, $username, $password] = match ($provider) {
            'stripe' => [
                'https://api.stripe.com/v1/account',
                [],
                $credentials['secret_key'] ?? '',
                '',
            ],
            'resend' => [
                'https://api.resend.com/domains',
                ['Authorization: Bearer ' . ($credentials['api_key'] ?? '')],
                null,
                null,
            ],
            'openai' => [
                'https://api.openai.com/v1/models',
                ['Authorization: Bearer ' . ($credentials['api_key'] ?? '')],
                null,
                null,
            ],
            default => throw new \InvalidArgumentException('Unknown provider.'),
        };

        $request = curl_init($url);
        if ($request === false) {
            throw new RuntimeException('Unable to initialize the provider connection check.');
        }

        curl_setopt_array($request, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'GarwinAI-Integration-Check/1.0',
        ]);
        if ($username !== null) {
            curl_setopt($request, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($request, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }

        $response = curl_exec($request);
        $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        $errorNumber = curl_errno($request);
        curl_close($request);

        if ($response === false || $errorNumber !== 0) {
            return ['success' => false, 'message' => 'Could not connect to the provider. Check network and TLS configuration.'];
        }
        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'message' => "Provider returned HTTP {$status}; check the credentials and API permissions."];
        }

        if ($provider === 'resend') {
            $domain = strtolower(substr(strrchr((string) $credentials['from_email'], '@') ?: '', 1));
            try {
                $payload = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [
                    'success' => false,
                    'message' => 'The provider returned an invalid response while validating the sender domain.',
                ];
            }
            $verifiedDomain = is_array($payload)
                && isset($payload['data'])
                && is_array($payload['data'])
                && count(array_filter(
                    $payload['data'],
                    static fn (mixed $entry): bool => is_array($entry)
                        && strtolower((string) ($entry['name'] ?? '')) === $domain
                        && strtolower((string) ($entry['status'] ?? '')) === 'verified'
                )) > 0;
            if (!$verifiedDomain) {
                return [
                    'success' => false,
                    'message' => 'Resend accepted the API key, but the sender domain is not verified there.',
                ];
            }

            return ['success' => true, 'message' => 'Resend API key and sender domain verified successfully.'];
        }

        if ($provider === 'stripe') {
            return [
                'success' => true,
                'message' => 'Stripe secret key accepted. Publishable and webhook values were format-checked, not API-tested.',
            ];
        }

        return ['success' => true, 'message' => 'OpenAI API key accepted.'];
    }
}
