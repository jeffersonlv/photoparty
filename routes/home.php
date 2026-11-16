<?php

use App\Http\Controllers\Home\HomeController;
use App\Http\Controllers\Home\LegalController;
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
