<?php

use App\Http\Controllers\PublicArticleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/articles', [PublicArticleController::class, 'index'])->name('public.articles.index');
Route::get('/articles/{slug}', [PublicArticleController::class, 'show'])->name('public.articles.show');

Route::get('/diagnostic-user', function () {
    $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIwMTllOTE0OC0yNDFmLTczYTctYjQ1YS01MGIzY2JmYmY2Y2MiLCJqdGkiOiJiMWQ5M2U4MGE0ZWUwYzE4ODg1MWI2ZjQ5OTdhMjM3YjIwNWE0ZTRhNDZiZTc5NWVjN2E0NzUyYTdkOTkwZjA4ZDBhOWM1YzJlOGUxODYyMSIsImlhdCI6MTc4MDU1Njk1OS43NjQ0MDcsIm5iZiI6MTc4MDU1Njk1OS43NjQ0MSwiZXhwIjoxNzgwNTg1NzU5Ljc0NzMyOCwic3ViIjoiMyIsInNjb3BlcyI6W119.EZwbbfW-6Hn6DIaTc7osDISRKqVSzVLAizaOIL6JIuChpNQoDvWKc6vAw06ofRjQS0wO5zFRCT97nC6dm4WATdHQtDWwAyXpYLcLg8xWldTZj8EsiApe5k59-pCG7AeNZ4WW0yvBIC0rAVyUnLxog02U1NqxG01QEDqhTbQ_nMbMotscLtb5ygjMC6URiBhCfg3pFcGpCAaL8GN02aaitQmk9IH7WOa1EjUEDYCdICHYI5l1ivxocFJAE5x9RQ5HCgJW1x1FUmTrmTAx2ChZEzOEpAZCPwbDzG8f1EJWTB5QjFrTip7sEIURnxIYAZpxfwEkHgslU8WKJty6HWPiatcxsRquqp99G6RyKeKgVZXOCQlxpnW76SzNrKDPVOSKdOb-wxmrPJV0mdy4Nb0Xg3S5I31bv2AavNFBWIm-vVtZA0NOshbacumSCfZagIFOiSc2pAxS-2ZVjtUp-zfg68tDnuk2EzEIGCYQ5ttYI_i8QqeCsT7YRvn0zRSjgiEJtMVBjkey8dGWeAJ_7AksMmlLl-ufcQOHN9-pSRPlHDVrRsbLRBJHlxtjspcMqrczAHU8RV2E0';
    
    $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    $psrHttpFactory = new \Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    
    $symfonyRequest = request()->duplicate();
    $symfonyRequest->headers->set('Authorization', 'Bearer ' . $token);
    
    $psrRequest = $psrHttpFactory->createRequest($symfonyRequest);
    
    $resourceServer = app(\League\OAuth2\Server\ResourceServer::class);
    
    $debug = [];
    try {
        $psrRequest = $resourceServer->validateAuthenticatedRequest($psrRequest);
        $tokenId = $psrRequest->getAttribute('oauth_access_token_id');
        $userId = $psrRequest->getAttribute('oauth_user_id');
        $clientId = $psrRequest->getAttribute('oauth_client_id');
        
        $debug['token_id'] = $tokenId;
        $debug['user_id'] = $userId;
        $debug['client_id'] = $clientId;
        
        $client = \Laravel\Passport\Passport::client()->find($clientId);
        $debug['client_found'] = $client !== null;
        if ($client) {
            $debug['client_name'] = $client->name;
        }
        
        $tokenModel = \Laravel\Passport\Passport::token()->find($tokenId);
        $debug['token_found'] = $tokenModel !== null;
        if ($tokenModel) {
            $debug['token_revoked'] = $tokenModel->revoked;
            $debug['token_expires_at'] = $tokenModel->expires_at;
        }
        
        $userProvider = auth()->guard('api')->getProvider();
        $user = $userProvider->retrieveById($userId);
        $debug['user_found'] = $user !== null;
        if ($user) {
            $debug['user_class'] = get_class($user);
            $debug['user_name'] = $user->name;
            $debug['user_is_active'] = $user->is_active;
        }
    } catch (\Throwable $e) {
        $debug['error'] = $e->getMessage();
        $debug['trace'] = $e->getTraceAsString();
    }
    
    return response()->json($debug);
});
