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
    
    $tokens = \DB::table('oauth_access_tokens')->where('user_id', $user->id)->get();
    
    return response()->json([
        'user' => $user->toArray(),
        'tokens' => $tokens->toArray(),
    ]);
});
