<?php

// Prepare temporary storage directories for Vercel Serverless environment
$storageDir = '/tmp/storage';
$dirs = [
    $storageDir . '/app',
    $storageDir . '/app/public',
    $storageDir . '/framework',
    $storageDir . '/framework/cache',
    $storageDir . '/framework/cache/data',
    $storageDir . '/framework/sessions',
    $storageDir . '/framework/views',
    $storageDir . '/logs',
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// Forward all Vercel Serverless requests to Laravel's public entry point
require __DIR__ . '/../public/index.php';
