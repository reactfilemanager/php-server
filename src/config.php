<?php

return [
    'root'    => __DIR__.'/../storage/',
    'cache'   => __DIR__.'/../.cache/',
    'uploads' => [
        'allowed_types' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/bmp',
        ]
    ]
];
