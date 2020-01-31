<?php

include 'vendor/autoload.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Method: *');
header('Access-Control-Allow-Headers: *');

if($_SERVER['REQUEST_METHOD'] === 'OPTIONS'){
    die;
}

try {
    (new \Rocky\FileManager\FileManager())->run();
} catch (\Psr\Cache\InvalidArgumentException $e) {
    http_response_code(500);
    echo json_encode(['message' => $e->getMessage()]);
}
