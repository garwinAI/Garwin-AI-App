<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Migrator;
use App\Support\Env;
use PDO;
use RuntimeException;

final class PlatformLaunchService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly IntegrationConfigService $integrations,
        private readonly ApiSecretCipher $cipher,
        private readonly StorageProvisioner $storage
    ) {
    }

    /** @return list<array{key: string, label: string, ready: bool, detail: string, fix: string}> */
    public function requirements(): array
    {
        $missingExtensions = [];
        foreach (['pdo', 'mbstring', 'session', 'curl', 'openssl', 'simplexml'] as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = 'ext-' . $extension;
            }
        }
        $phpReady = PHP_VERSION_ID >= 80200 && $missingExtensions === [];
        $appUrl = Env::get('APP_URL', '');
        $parsedUrl = is_string($appUrl) ? parse_url($appUrl) : false;
        $urlReady = is_array($parsedUrl)
            && ($parsedUrl['scheme'] ?? '') === 'https'
            && ($parsedUrl['host'] ?? '') === 'www.garwinai.com';
        $driver = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $driverExtension = match ($driver) {
            'mysql' => 'pdo_mysql',
            'pgsql' => 'pdo_pgsql',
            'sqlite' => 'pdo_sqlite',
            default => '',
        };
        $databaseReady = $driverExtension !== ''
            && extension_loaded($driverExtension)
            && (int) $this->pdo->query('SELECT 1')->fetchColumn() === 1;
        $pendingMigrations = (new Migrator($this->pdo))
            ->pending(dirname(__DIR__, 2) . '/database/migrations');
        $documentRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $publicRoot = realpath(dirname(__DIR__, 2) . '/public');
        $webRootReady = $documentRoot !== false && $documentRoot === $publicRoot;
        $storageReadiness = $this->storage->readiness($this->pdo);
        $dependenciesReady = is_file(dirname(__DIR__, 2) . '/vendor/autoload.php')
            && is_file(dirname(__DIR__, 2) . '/public/assets/css/bootstrap.min.css')
            && is_file(dirname(__DIR__, 2) . '/public/assets/js/admin.js');
        $requirements = [
            [
                'key' => 'php_runtime',
                'label' => 'PHP runtime and extensions',
                'ready' => $phpReady,
                'detail' => $phpReady
                    ? 'PHP ' . PHP_VERSION . ' and required extensions are available.'
                    : (PHP_VERSION_ID < 80200
                        ? 'PHP 8.2 or newer is required.'
                        : 'Missing extensions: ' . implode(', ', $missingExtensions) . '.'),
                'fix' => 'Use PHP 8.2+ and install the listed extensions (PDO, mbstring, session, cURL, OpenSSL, and SimpleXML). Enable pdo_pgsql for PostgreSQL, pdo_mysql for MySQL/MariaDB, or pdo_sqlite for SQLite. Restart PHP-FPM/web server and run composer install.',
            ],
            [
                'key' => 'composer_assets',
                'label' => 'Composer and frontend assets',
                'ready' => $dependenciesReady,
                'detail' => $dependenciesReady ? 'Composer autoload and local app assets are present.' : 'Required application or frontend assets are missing.',
                'fix' => 'From the project root run composer install, npm ci, and npm run build; deploy the generated vendor/ and public/assets/ files.',
            ],
            [
                'key' => 'database',
                'label' => 'Database connection',
                'ready' => $databaseReady,
                'detail' => $databaseReady ? 'PDO ' . $driver . ' connection is responding.' : 'The configured PDO database driver or connection is unavailable.',
                'fix' => 'Check DB_CONNECTION and the DB_* values in the server environment. Install the matching PDO driver (pdo_pgsql, pdo_mysql, or pdo_sqlite), then verify the database is reachable.',
            ],
            [
                'key' => 'migrations',
                'label' => 'Database migrations',
                'ready' => $pendingMigrations === [],
                'detail' => $pendingMigrations === []
                    ? 'All application migrations are applied.'
                    : count($pendingMigrations) . ' migration(s) are pending: ' . implode(', ', $pendingMigrations) . '.',
                'fix' => 'Back up the database, then run php bin/console migrate from the project root. Refresh this checklist to confirm.',
            ],
            [
                'key' => 'web_root',
                'label' => 'Public web root',
                'ready' => $webRootReady,
                'detail' => $webRootReady ? 'The web server document root is public/.' : 'The web server must serve only the public/ directory.',
                'fix' => 'Set the web server document root to the project’s public/ directory and restart the web server. Do not expose app/, database/, vendor/, .env, or var/.',
            ],
            [
                'key' => 'private_storage',
                'label' => 'Private storage',
                'ready' => $storageReadiness['ready'],
                'detail' => $storageReadiness['detail'],
                'fix' => $storageReadiness['fix'],
            ],
            [
                'key' => 'app_key',
                'label' => 'Credential encryption key',
                'ready' => $this->cipher->isConfigured(),
                'detail' => $this->cipher->isConfigured() ? 'Configured' : 'Set a 32-byte APP_KEY in the server environment.',
                'fix' => 'Run php bin/console key:generate, set the printed value as APP_KEY in the ignored .env/server environment, and keep a protected backup. Do not change the key after storing provider credentials.',
            ],
            [
                'key' => 'app_url',
                'label' => 'Production URL',
                'ready' => $urlReady,
                'detail' => $urlReady ? 'HTTPS production URL configured' : 'Set APP_URL to https://www.garwinai.com.',
                'fix' => 'Set APP_URL=https://www.garwinai.com in the server environment and restart the application.',
            ],
            [
                'key' => 'secure_cookie',
                'label' => 'Secure session cookies',
                'ready' => Env::bool('SESSION_SECURE_COOKIE'),
                'detail' => Env::bool('SESSION_SECURE_COOKIE') ? 'Enabled' : 'Set SESSION_SECURE_COOKIE=true.',
                'fix' => 'Terminate TLS at the web server/load balancer and set SESSION_SECURE_COOKIE=true in the server environment.',
            ],
            [
                'key' => 'debug',
                'label' => 'Debug mode disabled',
                'ready' => !Env::bool('APP_DEBUG'),
                'detail' => !Env::bool('APP_DEBUG') ? 'Disabled' : 'Set APP_DEBUG=false before launch.',
                'fix' => 'Set APP_DEBUG=false in the server environment and restart the application before launch.',
            ],
        ];

        foreach ($this->integrations->statuses() as $provider) {
            $verified = $provider['configured'] && $provider['verified_at'] !== null;
            $detail = !$provider['configured']
                ? 'Complete all credential fields.'
                : ($verified
                    ? (string) ($provider['last_test_message'] ?? 'Provider connection verified.')
                    : 'Save credentials and run a live connection test.');
            $requirements[] = [
                'key' => $provider['key'],
                'label' => $provider['label'] . ' connection',
                'ready' => $verified,
                'detail' => $detail,
                'fix' => 'Enter/update the ' . $provider['label'] . ' credentials below, then select Save & verify. Check the provider account permissions and verified sender/domain settings if the test fails.',
            ];
        }

        return $requirements;
    }

    public function isLaunchReady(): bool
    {
        foreach ($this->requirements() as $requirement) {
            if (!$requirement['ready']) {
                return false;
            }
        }

        return true;
    }

    public function isLaunched(): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT value_text FROM platform_settings WHERE setting_key = :setting_key'
        );
        $statement->execute(['setting_key' => 'platform_status']);

        return $statement->fetchColumn() === 'launch_ready' && $this->isLaunchReady();
    }

    public function markLaunchReady(): void
    {
        if (!$this->isLaunchReady()) {
            throw new RuntimeException('Complete and verify every launch requirement first.');
        }

        $now = gmdate('Y-m-d H:i:s');
        foreach (['platform_status' => 'launch_ready', 'platform_launched_at' => $now] as $key => $value) {
            $find = $this->pdo->prepare(
                'SELECT 1 FROM platform_settings WHERE setting_key = :setting_key'
            );
            $find->execute(['setting_key' => $key]);
            $exists = $find->fetchColumn() !== false;
            $statement = $this->pdo->prepare(
                'UPDATE platform_settings SET value_text = :value_text, updated_at = :updated_at
                 WHERE setting_key = :setting_key'
            );
            $statement->execute([
                'value_text' => $value,
                'updated_at' => $now,
                'setting_key' => $key,
            ]);
            if (!$exists) {
                $insert = $this->pdo->prepare(
                    'INSERT INTO platform_settings (setting_key, value_text, updated_at)
                     VALUES (:setting_key, :value_text, :updated_at)'
                );
                $insert->execute([
                    'setting_key' => $key,
                    'value_text' => $value,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
