<?php

use App\Http\Controllers\Moderation\ModerationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo 6: moderação ao vivo (carregado com o grupo "web" por bootstrap/app.php)
|--------------------------------------------------------------------------
|
| auth + e-mail verificado + conta. {event} pelo uuid com o global scope da conta (outra
| conta => 404); scopeBindings() amarra {photo} ao evento. Galeria e ZIP vêm depois.
|
*/

Route::middleware(['auth', 'verified', 'account'])->group(function () {
    Route::scopeBindings()->group(function () {
        Route::get('/events/{event}/moderation', [ModerationController::class, 'index'])
            ->name('events.moderation');

        Route::middleware('throttle:moderation')->group(function () {
            Route::get('/events/{event}/moderation/photos', [ModerationController::class, 'photos'])
                ->name('events.moderation.photos');

            Route::post('/events/{event}/moderation/batch', [ModerationController::class, 'batch'])
                ->name('events.moderation.batch');

            Route::post('/events/{event}/moderation/photos/{photo}/{action}', [ModerationController::class, 'update'])
                ->whereIn('action', ['approve', 'reject'])
                ->name('events.moderation.photo');
        });
    });
});
