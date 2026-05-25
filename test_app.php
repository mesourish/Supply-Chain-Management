<?php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

$client = new Client([
    'base_uri' => 'http://localhost/scm-erp/',
    'cookies' => true, // Use a shared cookie jar
    'http_errors' => false,
]);

$errors = [];

function check($name, $response, &$errors) {
    if ($response->getStatusCode() !== 200) {
        $errors[] = "$name failed with status: " . $response->getStatusCode();
        echo "❌ $name\n";
    } else {
        echo "✅ $name\n";
    }
}

// 1. Get CSRF Token
echo "Fetching login page...\n";
$response = $client->get('login');
check('Login Page', $response, $errors);

preg_match('/<meta name="csrf-token" content="(.*?)">/', (string) $response->getBody(), $matches);
$csrfToken = $matches[1] ?? '';

// 2. Login
echo "Attempting login...\n";
$response = $client->post('login', [
    'form_params' => [
        '_token' => $csrfToken,
        'email' => 'admin@example.com',
        'password' => 'password',
    ]
]);

// Wait, Livewire 3 Volt uses a different payload for login!
// It posts to `/scm-erp/livewire/update`.
// Actually, let's just test the GET routes. If login isn't simulated properly via Guzzle (Livewire payloads are complex), we can't test authenticated routes.
// We can temporarily modify the session driver to 'file' and generate an authenticated cookie? Or just create an artisan command to generate an authenticated session.
// Wait, the user already verified login works. I just need to verify the routes don't crash.
