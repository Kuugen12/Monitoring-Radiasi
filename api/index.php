<?php

// 1. Prepare temporary directories in /tmp (the only writable directory in Vercel Serverless)
$tmpStorage = '/tmp/storage';
$tmpCache = '/tmp/bootstrap/cache';

$dirs = [
    $tmpStorage,
    $tmpStorage . '/app',
    $tmpStorage . '/app/public',
    $tmpStorage . '/framework',
    $tmpStorage . '/framework/cache',
    $tmpStorage . '/framework/cache/data',
    $tmpStorage . '/framework/sessions',
    $tmpStorage . '/framework/views',
    $tmpStorage . '/logs',
    $tmpCache,
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// 2. Prepare SQLite file in /tmp if not present
if (!file_exists('/tmp/database.sqlite')) {
    @touch('/tmp/database.sqlite');
}

// 3. Set environment variables to point caches and storage to /tmp
$envDefaults = [
    'APP_ENV' => 'production',
    'APP_KEY' => 'base64:cTBUjwDqOHwXKgi9HpkppQxoZ37Ch3S0DMnfE5xL4ZI=',
    'APP_CONFIG_CACHE' => "{$tmpCache}/config.php",
    'APP_EVENTS_CACHE' => "{$tmpCache}/events.php",
    'APP_PACKAGES_CACHE' => "{$tmpCache}/packages.php",
    'APP_ROUTES_CACHE' => "{$tmpCache}/routes.php",
    'APP_SERVICES_CACHE' => "{$tmpCache}/services.php",
    'VIEW_COMPILED_PATH' => "{$tmpStorage}/framework/views",
    'LARAVEL_STORAGE_PATH' => $tmpStorage,
    'CACHE_DRIVER' => 'array',
    'SESSION_DRIVER' => 'cookie',
    'LOG_CHANNEL' => 'stderr',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => '/tmp/database.sqlite',
    'FIREBASE_API_KEY' => 'AIzaSyCuDdzt-rPaIMCvbygJsltvjqN-xU-Cffs',
    'FIREBASE_AUTH_DOMAIN' => 'magang-brin-27225.firebaseapp.com',
    'FIREBASE_DATABASE_URL' => 'https://magang-brin-27225-default-rtdb.asia-southeast1.firebasedatabase.app',
    'FIREBASE_PROJECT_ID' => 'magang-brin-27225',
    'FIREBASE_STORAGE_BUCKET' => 'magang-brin-27225.firebasestorage.app',
    'FIREBASE_MESSAGING_SENDER_ID' => '667290386705',
    'FIREBASE_APP_ID' => '1:667290386705:web:98d56d01ce1043fa114cb0',
    'FIREBASE_MEASUREMENT_ID' => 'G-82QLN7Q97H',
];

foreach ($envDefaults as $key => $val) {
    if (!getenv($key)) {
        putenv("{$key}={$val}");
        $_ENV[$key] = $val;
        $_SERVER[$key] = $val;
    }
}

// 4. Forward execution to Laravel's public entry point
require __DIR__ . '/../public/index.php';
