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
        mkdir($dir, 0777, true);
    }
}

// 2. Set environment variables to point caches and storage to /tmp
putenv("APP_CONFIG_CACHE={$tmpCache}/config.php");
putenv("APP_EVENTS_CACHE={$tmpCache}/events.php");
putenv("APP_PACKAGES_CACHE={$tmpCache}/packages.php");
putenv("APP_ROUTES_CACHE={$tmpCache}/routes.php");
putenv("APP_SERVICES_CACHE={$tmpCache}/services.php");
putenv("VIEW_COMPILED_PATH={$tmpStorage}/framework/views");
putenv("LARAVEL_STORAGE_PATH={$tmpStorage}");

$_ENV['APP_CONFIG_CACHE'] = "{$tmpCache}/config.php";
$_ENV['APP_EVENTS_CACHE'] = "{$tmpCache}/events.php";
$_ENV['APP_PACKAGES_CACHE'] = "{$tmpCache}/packages.php";
$_ENV['APP_ROUTES_CACHE'] = "{$tmpCache}/routes.php";
$_ENV['APP_SERVICES_CACHE'] = "{$tmpCache}/services.php";
$_ENV['VIEW_COMPILED_PATH'] = "{$tmpStorage}/framework/views";
$_ENV['LARAVEL_STORAGE_PATH'] = $tmpStorage;

$_SERVER['APP_CONFIG_CACHE'] = "{$tmpCache}/config.php";
$_SERVER['APP_EVENTS_CACHE'] = "{$tmpCache}/events.php";
$_SERVER['APP_PACKAGES_CACHE'] = "{$tmpCache}/packages.php";
$_SERVER['APP_ROUTES_CACHE'] = "{$tmpCache}/routes.php";
$_SERVER['APP_SERVICES_CACHE'] = "{$tmpCache}/services.php";
$_SERVER['VIEW_COMPILED_PATH'] = "{$tmpStorage}/framework/views";
$_SERVER['LARAVEL_STORAGE_PATH'] = $tmpStorage;

// 3. Fallback driver settings for serverless
if (!getenv('CACHE_DRIVER')) {
    putenv('CACHE_DRIVER=array');
    $_ENV['CACHE_DRIVER'] = 'array';
    $_SERVER['CACHE_DRIVER'] = 'array';
}
if (!getenv('SESSION_DRIVER')) {
    putenv('SESSION_DRIVER=cookie');
    $_ENV['SESSION_DRIVER'] = 'cookie';
    $_SERVER['SESSION_DRIVER'] = 'cookie';
}
if (!getenv('LOG_CHANNEL')) {
    putenv('LOG_CHANNEL=stderr');
    $_ENV['LOG_CHANNEL'] = 'stderr';
    $_SERVER['LOG_CHANNEL'] = 'stderr';
}

// 4. Forward execution to Laravel's public entry point
require __DIR__ . '/../public/index.php';
