<?php

use App\Http\Controllers\PublicArticleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/articles', [PublicArticleController::class, 'index'])->name('public.articles.index');
Route::get('/articles/{slug}', [PublicArticleController::class, 'show'])->name('public.articles.show');

use Psr\Http\Message\ServerRequestInterface;

Route::get('/diagnostic-user', function (ServerRequestInterface $request) {
    $resourceServer = app(\League\OAuth2\Server\ResourceServer::class);
    
    try {
        $psrRequest = $resourceServer->validateAuthenticatedRequest($request);
        $userId = $psrRequest->getAttribute('oauth_user_id');
        $tokenId = $psrRequest->getAttribute('oauth_access_token_id');
        $clientId = $psrRequest->getAttribute('oauth_client_id');
        
        $user = \App\Models\User::find($userId);
        
        return response()->json([
            'success' => true,
            'user_id' => $userId,
            'token_id' => $tokenId,
            'client_id' => $clientId,
            'user_found' => $user !== null,
            'user' => $user ? $user->toArray() : null,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error_class' => get_class($e),
            'error_message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
});
