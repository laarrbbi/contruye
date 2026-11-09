<?php

/*
|--------------------------------------------------------------------------
| Vercel preview: the entry point of the API function
|--------------------------------------------------------------------------
|
| vercel.json sends /api/* and /sanctum/* here; the vercel-php runtime runs it
| with PHP's built-in server. It sets the preview's settings, then hands the
| request to Laravel (public/index.php) as a normal server would.
|
| A function's disk is read-only except /tmp: the demo database built at
| deploy time (deploy/vercel/build.sh) is copied there on a cold start, and
| Laravel's storage lives there. What a visitor changes lasts while the
| instance is warm and is gone on the next one: a preview, not a live site.
| The live site is deployed with deploy/deploy.sh (deploy/README.md).
*/

$tmp = '/tmp/construye';
foreach (['storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $dir) {
    if (! is_dir("{$tmp}/{$dir}")) {
        mkdir("{$tmp}/{$dir}", 0777, true);
    }
}
if (! is_file("{$tmp}/database.sqlite")) {
    copy(__DIR__.'/database/preview.sqlite', "{$tmp}/database.sqlite");
}

$host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
$url = "https://{$host}";

$settings = [
    'APP_NAME' => 'Construye Academy',
    'APP_ENV' => 'preview',
    'APP_DEBUG' => 'false',
    'APP_KEY' => trim((string) file_get_contents(__DIR__.'/storage/app/vercel-app-key')),
    'APP_URL' => $url,
    'FRONTEND_URL' => $url,
    'APP_LOCALE' => 'es',
    'APP_FALLBACK_LOCALE' => 'es',
    'LARAVEL_STORAGE_PATH' => "{$tmp}/storage",
    'APP_SERVICES_CACHE' => "{$tmp}/services.php",
    'APP_CONFIG_CACHE' => "{$tmp}/config.php",
    'APP_ROUTES_CACHE' => "{$tmp}/routes.php",
    'APP_EVENTS_CACHE' => "{$tmp}/events.php",
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => "{$tmp}/database.sqlite",
    // The session travels in its (encrypted) cookie: no shared store between instances.
    'SESSION_DRIVER' => 'cookie',
    'SESSION_SECURE_COOKIE' => 'true',
    'SESSION_DOMAIN' => '',
    'SANCTUM_STATEFUL_DOMAINS' => $host,
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'log',
    'LOG_CHANNEL' => 'stderr',
    'FILESYSTEM_DISK' => 'local',
    // The template engine runs in this same function (financials/cli.js).
    'PLANTILLA_TRANSPORT' => 'process',
    // The Module 1 film, served as a file by Vercel (web/public/video).
    'VIDEO_FILES_BASE' => '/video',
    // Demo payment details, as in development: nothing can be paid on a preview.
    'PAYMENT_TRANSFER_IBAN' => 'ES00 0000 0000 0000 0000 0000',
    'PAYMENT_TRANSFER_HOLDER' => '[DEMO] Construye Academy S.L.',
    'PAYMENT_BIZUM_PHONE' => '600000000',
];
foreach ($settings as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

// Vercel ends HTTPS; Laravel should see the request as the visitor made it.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}

// As if public/index.php were the script: Laravel then routes /api/... in full.
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__.'/public/index.php';
