<?php

declare(strict_types=1);

// When the project root (not public/) is served — e.g. `php -S localhost:8080`
// from the repository, or a preview host — serve public/ assets so pages keep
// their CSS, JavaScript, and icons. Production should point at public/.
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (is_string($requestPath) && $requestPath !== '/') {
    $publicRoot = __DIR__ . '/public';
    $asset = realpath($publicRoot . '/' . ltrim(rawurldecode($requestPath), '/'));
    $types = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'ico' => 'image/x-icon',
        'webmanifest' => 'application/manifest+json',
        'woff2' => 'font/woff2',
    ];
    $extension = $asset !== false ? strtolower(pathinfo($asset, PATHINFO_EXTENSION)) : '';
    if (
        $asset !== false
        && is_file($asset)
        && str_starts_with($asset, $publicRoot . DIRECTORY_SEPARATOR)
        && isset($types[$extension])
    ) {
        header('Content-Type: ' . $types[$extension]);
        header('Content-Length: ' . (string) filesize($asset));
        header('X-Content-Type-Options: nosniff');
        readfile($asset);
        return;
    }
}

require __DIR__ . '/public/index.php';
