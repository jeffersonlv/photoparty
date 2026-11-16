<?php

use App\Http\Controllers\Screen\ScreenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo 5: telão (carregado com o grupo "web" por bootstrap/app.php)
|--------------------------------------------------------------------------
|
| Sem login. O evento é localizado pelo hash do token (Event::whereScreenToken);
| token inválido/revogado => 404. Só fotos aprovadas daquele evento, com URL assinada.
|
*/

$token = '[A-Za-z0-9]{64}';

Route::get('/screen/{token}', [ScreenController::class, 'show'])
    ->where('token', $token)
    ->middleware('throttle:screen')
    ->name('screen.show');

Route::get('/screen/{token}/photos', [ScreenController::class, 'photos'])
    ->where('token', $token)
    ->middleware('throttle:screen')
    ->name('screen.photos');
