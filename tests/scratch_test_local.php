<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

use Illuminate\Http\Request;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Force application to bootstrap
$app->bootstrapWith([
    \Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::class,
    \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
    \Illuminate\Foundation\Bootstrap\HandleExceptions::class,
    \Illuminate\Foundation\Bootstrap\RegisterProviders::class,
    \Illuminate\Foundation\Bootstrap\BootProviders::class,
]);

// Set GOOGLE_CLIENT_ID in config so verifier doesn't throw SocialAuthNotConfiguredException
config(['services.google.client_id' => 'dummy_client_id']);

$request = Request::create('/api/v1/auth/social', 'POST', [
    'provider' => 'google',
    'token' => 'dummy_token_to_check_config_status',
]);

$response = $kernel->handle($request);

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Response Body: \n" . $response->getContent() . "\n";

$kernel->terminate($request, $response);
