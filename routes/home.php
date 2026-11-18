<?php

use App\Http\Controllers\Home\HomeController;
use App\Http\Controllers\Home\LegalController;
use App\Http\Controllers\Privacy\PrivacyRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Página inicial pública e páginas legais
|--------------------------------------------------------------------------
|
| Carregado por bootstrap/app.php com o middleware "web". Conteúdo editável
| em /admin/home; resultado em cache por idioma (HomeContent).
|
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/termos', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/privacidade', [LegalController::class, 'privacy'])->name('legal.privacy');

// Solicitação do titular (LGPD art. 18 / GDPR arts. 15-22): público, honeypot + rate limit por IP.
Route::get('/privacidade/solicitacao', [PrivacyRequestController::class, 'create'])->name('privacy.request.create');
Route::post('/privacidade/solicitacao', [PrivacyRequestController::class, 'store'])
    ->middleware('throttle:privacy-request')
    ->name('privacy.request.store');
