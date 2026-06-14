<?php

use App\Http\Controllers\GroupScoringInviteController;
use App\Http\Controllers\PublicArticleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/articles', [PublicArticleController::class, 'index'])->name('public.articles.index');
Route::get('/articles/{slug}', [PublicArticleController::class, 'show'])->name('public.articles.show');

/*
|--------------------------------------------------------------------------
| Latihan Bersama — public invite landing + deep-link verification (Sprint 09)
|--------------------------------------------------------------------------
| The HTTPS share link (https://<host>/j/{code}) opens the app via verified
| App Links / Universal Links; without the app it renders the invite landing.
*/
Route::get('/j/{code}', [GroupScoringInviteController::class, 'show'])
    ->where('code', '[A-Za-z0-9]{4,12}')
    ->name('group-scoring.invite');
Route::get('/group-scoring/join', [GroupScoringInviteController::class, 'show'])
    ->name('group-scoring.invite.query');

// App Links / Universal Links association files (served as application/json).
Route::get('/.well-known/assetlinks.json', [GroupScoringInviteController::class, 'assetLinks']);
Route::get('/.well-known/apple-app-site-association', [GroupScoringInviteController::class, 'appleAppSiteAssociation']);
Route::get('/apple-app-site-association', [GroupScoringInviteController::class, 'appleAppSiteAssociation']);
