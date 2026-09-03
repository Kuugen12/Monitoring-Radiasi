<?php

// Enable error reporting during initialization to help diagnose
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

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

// 2. Set environment variables to point caches and storage to /tmp
putenv("APP_CONFIG_CACHE={$tmpCache}/config.php");
putenv("APP_EVENTS_CACHE={$tmpCache}/events.php");
putenv("APP_PACKAGES_CACHE={$tmpCache}/packages.php");
putenv("APP_ROUTES_CACHE={$tmpCache}/routes.php");
putenv("APP_SERVICES_CACHE={$tmpCache}/services.php");
putenv("VIEW_COMPILED_PATH={$tmpStorage}/framework/views");
putenv("LARAVEL_STORAGE_PATH={$tmpStorage}");

$_ENV['LARAVEL_STORAGE_PATH'] = $tmpStorage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $tmpStorage;
$_ENV['VIEW_COMPILED_PATH'] = "{$tmpStorage}/framework/views";
$_SERVER['VIEW_COMPILED_PATH'] = "{$tmpStorage}/framework/views";

// 3. Forward execution to Laravel's public entry point
require __DIR__ . '/../public/index.php';

