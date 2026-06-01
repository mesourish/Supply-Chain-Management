<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Set SCRIPT_NAME so Symfony/Laravel Request correctly detects the subfolder base path.
// This is the proper way to handle subfolder installs - do NOT strip REQUEST_URI.
// Read APP_URL from .env to determine the subfolder path dynamically.
$_appEnvUrl = '';
$_envFile = __DIR__ . '/../.env';
if (is_readable($_envFile)) {
    $_envContent = @file_get_contents($_envFile);
    if ($_envContent !== false && preg_match('/^APP_URL=(.+)$/m', $_envContent, $_matches)) {
        $_appEnvUrl = trim($_matches[1], " \t\n\r\0\x0B\"'");
    }
    unset($_envContent, $_matches);
}
$_subPath = rtrim(parse_url($_appEnvUrl, PHP_URL_PATH) ?? '', '/');
if (!empty($_subPath)) {
    $_SERVER['SCRIPT_NAME'] = $_subPath . '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
}
unset($_appEnvUrl, $_envFile, $_subPath);

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
