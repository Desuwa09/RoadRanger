<?php
require_once __DIR__ . '/../pages/admin/module_schema.php';

$generated = [
    'nodes' => [
        'start' => [
            'bot_message' => 'At a pedestrian crossing, stop and wait for the pedestrian.',
            'image' => 'https://example.com/crossing.png',
            'choices' => [
                ['text' => 'I will stop and wait.', 'next_node' => 'end']
            ]
        ],
        'end' => [
            'bot_message' => 'Good choice. Always yield to pedestrians.',
            'choices' => []
        ]
    ]
];

$normalized = RoadRanger\ModuleSchema::normalizeGeneratedModule($generated, 'Pedestrian Safety');

if (!isset($normalized['summary']) || trim($normalized['summary']) === '') {
    fwrite(STDERR, "Missing summary\n");
    exit(1);
}

if (!isset($normalized['quiz']) || !is_array($normalized['quiz']) || count($normalized['quiz']) < 1) {
    fwrite(STDERR, "Missing quiz\n");
    exit(1);
}

if (!isset($normalized['cover_image']) || trim((string)$normalized['cover_image']) === '') {
    fwrite(STDERR, "Missing cover image\n");
    exit(1);
}

echo "Module schema contract passed\n";
