<?php
/**
 * PWA Manifest for Equipment Manager Service Reports
 */

header('Content-Type: application/manifest+json');
header('Cache-Control: max-age=86400');

$manifest = [
    'name' => 'Serviceberichte',
    'short_name' => 'Service',
    'description' => 'Offline Serviceberichte für Techniker',
    'start_url' => './index.php',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'portrait',
    'background_color' => '#ffffff',
    'theme_color' => '#263c5c',
    'icons' => [
        [
            'src' => '../img/object_equipment.png',
            'sizes' => '32x32',
            'type' => 'image/png'
        ],
        [
            'src' => 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 192 192"><rect fill="#263c5c" width="192" height="192" rx="20"/><text x="96" y="130" font-size="100" text-anchor="middle" fill="white">S</text></svg>'),
            'sizes' => '192x192',
            'type' => 'image/svg+xml',
            'purpose' => 'any maskable'
        ]
    ],
    'categories' => ['business', 'productivity'],
    'lang' => 'de',
    'dir' => 'ltr'
];

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
