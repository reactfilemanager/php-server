<?php

include 'vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Method: *');
header('Access-Control-Allow-Headers: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    die;
}

try {
    (new \ThemeXpert\FileManager\FileManager([
        'root'    => __DIR__.'/tmp/storage/',
        'cache'   => __DIR__.'/tmp/.cache/',
        'uploads' => [
            'max_upload_size' => 0,
            'mime_check'      => true,
            'allowed_types'   => [
                'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/svg\+xml', 'image/svg'
            ]
        ]
    ]))->run();
} catch (\Psr\Cache\InvalidArgumentException $e) {
    http_response_code(500);
    echo json_encode(['message' => $e->getMessage()]);
}
