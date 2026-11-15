<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $catalog = [
        [
            'name' => 'Garwin Developer',
            'description' => 'Developer documentation and package standards for Garwin apps, widgets, and themes.',
            'launch_url' => '/developer',
            'price_cents' => 0,
        ],
        [
            'name' => 'Focus Timer',
            'description' => 'A lightweight widget for planning work blocks, breaks, and deep-focus sessions.',
            'launch_url' => '/dashboard',
            'price_cents' => 0,
        ],
        [
            'name' => 'Weather Tile',
            'description' => 'A small dashboard widget for current conditions, forecasts, and quick weather summaries.',
            'launch_url' => '/dashboard',
            'price_cents' => 0,
        ],
        [
            'name' => 'Night Pulse',
            'description' => 'A dark, focused theme pack with calmer contrast, accent colors, and a streamlined layout.',
            'launch_url' => '/settings',
            'price_cents' => 0,
        ],
        [
            'name' => 'Client Brief',
            'description' => 'An app for summaries, action items, and shared meeting notes from the workspace.',
            'launch_url' => '/dashboard',
            'price_cents' => 0,
        ],
        [
            'name' => 'Skyline Glass',
            'description' => 'A polished theme collection with airy surfaces, glass edges, and bright workspace accents.',
            'launch_url' => '/settings',
            'price_cents' => 0,
        ],
    ];

    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        'INSERT INTO appzone_items
         (name, description, launch_url, price_cents, is_published, view_count, purchase_count, created_at, updated_at)
         VALUES (:name, :description, :launch_url, :price_cents, :is_published, :view_count, :purchase_count, :created_at, :updated_at)'
    );

    foreach ($catalog as $entry) {
        $existing = $pdo->prepare('SELECT 1 FROM appzone_items WHERE name = :name LIMIT 1');
        $existing->execute(['name' => $entry['name']]);
        if ($existing->fetchColumn() !== false) {
            continue;
        }

        $insert->execute([
            'name' => $entry['name'],
            'description' => $entry['description'],
            'launch_url' => $entry['launch_url'],
            'price_cents' => $entry['price_cents'],
            'is_published' => 1,
            'view_count' => 0,
            'purchase_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
