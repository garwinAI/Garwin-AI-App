<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;

/**
 * Default apps that ship with every workspace. Media Viewer and Document Viewer
 * are on every plan; the productivity apps need a paid membership.
 */
final class BuiltInApps
{
    public const FREE_PLAN = 'free';

    /** @var array<string, array{name: string, path: string, description: string, free: bool, tone: string}> */
    private const APPS = [
        'wordbase' => [
            'name' => 'Wordbase',
            'path' => '/wordbase',
            'description' => 'Write notes, plans and documents.',
            'free' => false,
            'tone' => 'blue',
        ],
        'spreadsheets' => [
            'name' => 'Spreadsheets',
            'path' => '/spreadsheets',
            'description' => 'Tables with formulas like SUM and AVERAGE.',
            'free' => false,
            'tone' => 'green',
        ],
        'codebase' => [
            'name' => 'Codebase',
            'path' => '/codebase',
            'description' => 'Save and edit code files.',
            'free' => false,
            'tone' => 'slate',
        ],
        'media' => [
            'name' => 'Media Viewer',
            'path' => '/media',
            'description' => 'View photos, videos and audio from your drive.',
            'free' => true,
            'tone' => 'violet',
        ],
        'documents' => [
            'name' => 'Document Viewer',
            'path' => '/documents',
            'description' => 'Read PDFs, text and CSV files from your drive.',
            'free' => true,
            'tone' => 'red',
        ],
    ];

    /** @return array{name: string, path: string, description: string, free: bool, tone: string} */
    public static function get(string $key): array
    {
        return self::APPS[$key] ?? throw new \InvalidArgumentException('Unknown app.');
    }

    /** @return list<string> */
    public static function paths(): array
    {
        return array_values(array_map(static fn (array $app): string => $app['path'], self::APPS));
    }

    /** @return array<string, array{name: string, path: string, description: string, free: bool, tone: string}> */
    public static function forPlan(string $planKey): array
    {
        return array_filter(self::APPS, static fn (array $app): bool => $app['free'] || $planKey !== self::FREE_PLAN);
    }

    public static function isAvailable(string $key, string $planKey): bool
    {
        return array_key_exists($key, self::forPlan($planKey));
    }

    public static function planKeyFor(int $userId): string
    {
        try {
            $statement = app_db()->prepare(
                "SELECT plan_key FROM memberships WHERE user_id = :user_id AND status IN ('active', 'trialing') LIMIT 1"
            );
            $statement->execute(['user_id' => $userId]);
            $planKey = $statement->fetchColumn();
        } catch (PDOException) {
            return self::FREE_PLAN;
        }

        return is_string($planKey) && $planKey !== '' ? $planKey : self::FREE_PLAN;
    }

    /**
     * Returns the signed-in workspace user, or stops with a login redirect or an
     * upgrade page when their plan does not include the app.
     */
    public static function requireAccess(string $key): array
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if ($user['is_admin']) {
            redirect('/admin');
        }

        if (!self::isAvailable($key, self::planKeyFor((int) $user['id']))) {
            render('apps/locked', [
                'title' => self::get($key)['name'],
                'user' => $user,
                'appKey' => $key,
                'app' => self::get($key),
            ], 403);
            exit;
        }

        return $user;
    }
}
