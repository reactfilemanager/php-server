<?php

return [
    'root'    => __DIR__.'/../tmp/storage/',
    'cache'   => __DIR__.'/../tmp/.cache/',
    'uploads' => [
        'allowed_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/svg\+xml', 'image/svg'
        ]
    ]
];
