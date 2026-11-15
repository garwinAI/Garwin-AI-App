<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

App\Support\Env::load(__DIR__ . '/.env');

function app_db(): PDO
{
    static $connection;

    if (!$connection instanceof PDO) {
        $connection = App\Database\Connection::connect();
    }

    return $connection;
}
