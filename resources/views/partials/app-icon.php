<?php
/**
 * Thumbnail icon tile for a built-in app.
 *
 * @var string $iconKey  Built-in app key.
 * @var string $iconSize '' | 'is-large'
 */
$iconSize ??= '';
$glyphs = [
    'wordbase' => '<path d="M7 3.5h7l4 4V20a.5.5 0 0 1-.5.5h-10A.5.5 0 0 1 7 20V3.5Z"/><path d="M14 3.5V8h4M9.5 12h6M9.5 15h6M9.5 18h4"/>',
    'spreadsheets' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M4 9.5h16M4 15h16M10 4v16"/>',
    'codebase' => '<path d="m8.5 8-4 4 4 4M15.5 8l4 4-4 4M13.2 5.5l-2.4 13"/>',
    'media' => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m4 17 5-4.5 3.5 3 3-2.5 4.5 4"/>',
    'documents' => '<path d="M7 3.5h7l4 4V20a.5.5 0 0 1-.5.5h-10A.5.5 0 0 1 7 20V3.5Z"/><path d="M14 3.5V8h4"/><path d="M9.5 13.5h5M9.5 16.5h5"/>',
];
$tone = App\Support\BuiltInApps::get($iconKey)['tone'];
?>
<span class="app-icon tone-<?= e($tone) ?> <?= e($iconSize) ?>" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $glyphs[$iconKey] ?></svg></span>
