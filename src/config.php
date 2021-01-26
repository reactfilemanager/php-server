<?php

return [
    'root'    => null,
    'cache'   => null,
    'uploads' => [
        'max_upload_size' => 50,
        'mime_check' => false,
        'allowed_types' => ['.*'],
    ],
    'plugins' => [
        'core' => \ThemeXpert\FileManager\Plugins\Core::class,
        // 'pjpeg' => \ThemeXpert\FileManager\Plugins\ProgressiveJPEG::class,
    ]
];
