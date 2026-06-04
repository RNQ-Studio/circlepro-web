<?php

use App\Http\Controllers\PublicArticleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/articles', [PublicArticleController::class, 'index'])->name('public.articles.index');
Route::get('/articles/{slug}', [PublicArticleController::class, 'show'])->name('public.articles.show');

Route::get('/diagnostic-user', function () {
    $user = \App\Models\User::withTrashed()->find(3);
    if (!$user) {
        return response()->json(['error' => 'User 3 not found']);
    }
    
    $token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIwMTllOTE0OC0yNDFmLTczYTctYjQ1YS01MGIzY2JmYmY2Y2MiLCJqdGkiOiJiMWQ5M2U4MGE0ZWUwYzE4ODg1MWI2ZjQ5OTdhMjM3YjIwNWE0ZTRhNDZiZTc5NWVjN2E0NzUyYTdkOTkwZjA4ZDBhOWM1YzJlOGUxODYyMSIsImlhdCI6MTc4MDU1Njk1OS43NjQ0MDcsIm5iZiI6MTc4MDU1Njk1OS43NjQ0MSwiZXhwIjoxNzgwNTg1NzU5Ljc0NzMyOCwic3ViIjoiMyIsInNjb3BlcyI6W119.EZwbbfW-6Hn6DIaTc7osDISRKqVSzVLAizaOIL6JIuChpNQoDvWKc6vAw06ofRjQS0wO5zFRCT97nC6dm4WATdHQtDWwAyXpYLcLg8xWldTZj8EsiApe5k59-pCG7AeNZ4WW0yvBIC0rAVyUnLxog02U1NqxG01QEDqhTbQ_nMbMotscLtb5ygjMC6URiBhCfg3pFcGpCAaL8GN02aaitQmk9IH7WOa1EjUEDYCdICHYI5l1ivxocFJAE5x9RQ5HCgJW1x1FUmTrmTAx2ChZEzOEpAZCPwbDzG8f1EJWTB5QjFrTip7sEIURnxIYAZpxfwEkHgslU8WKJty6HWPiatcxsRquqp99G6RyKeKgVZXOCQlxpnW76SzNrKDPVOSKdOb-wxmrPJV0mdy4Nb0Xg3S5I31bv2AavNFBWIm-vVtZA0NOshbacumSCfZagIFOiSc2pAxS-2ZVjtUp-zfg68tDnuk2EzEIGCYQ5ttYI_i8QqeCsT7YRvn0zRSjgiEJtMVBjkey8dGWeAJ_7AksMmlLl-ufcQOHN9-pSRPlHDVrRsbLRBJHlxtjspcMqrczAHU8RV2E0';
    
    // Simulate the Passport guard authentication
    $request = request()->duplicate();
    $request->headers->set('Authorization', 'Bearer ' . $token);
    
    $authenticatedUser = null;
    $errorMessage = null;
    
    try {
        // Authenticate the request manually using Passport guard
        $authenticatedUser = auth()->guard('api')->user($request);
    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage() . "\n" . $e->getTraceAsString();
    }
    
    return response()->json([
        'user' => $user->toArray(),
        'auth_user_resolved' => $authenticatedUser !== null,
        'auth_user' => $authenticatedUser ? $authenticatedUser->toArray() : null,
        'error' => $errorMessage,
    ]);
});
