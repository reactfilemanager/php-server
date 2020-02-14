<?php

return [
    'root'    => null,
    'cache'   => null,
    'uploads' => [
        'max_upload_size' => 0,
        'mime_check' => false,
        'allowed_types' => [],
    ],
    'plugins' => [
        'Core' => \Rocky\FileManager\Plugins\Core::class,
    ]
];
