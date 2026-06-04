<?php

use App\Http\Controllers\PublicArticleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/articles', [PublicArticleController::class, 'index'])->name('public.articles.index');
Route::get('/articles/{slug}', [PublicArticleController::class, 'show'])->name('public.articles.show');

Route::get('/diagnostic-user', function () {
    $clients = \DB::table('oauth_clients')->get();
    
    return response()->json([
        'clients' => $clients->toArray(),
    ]);
});
