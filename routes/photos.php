<?php

use App\Http\Controllers\Moderation\ModerationController;
use App\Http\Controllers\Gallery\ExportController;
use App\Http\Controllers\Gallery\GalleryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo 6: moderação ao vivo (carregado com o grupo "web" por bootstrap/app.php)
|--------------------------------------------------------------------------
|
| auth + e-mail verificado + conta. {event} pelo uuid com o global scope da conta (outra
| conta => 404); scopeBindings() amarra {photo} ao evento. Módulo 7: galeria, download e ZIP.
|
*/

Route::middleware(['auth', 'verified', 'account'])->group(function () {
    Route::scopeBindings()->group(function () {
        Route::get('/events/{event}/moderation', [ModerationController::class, 'index'])
            ->name('events.moderation');

        Route::get('/events/{event}/gallery', [GalleryController::class, 'index'])
            ->name('events.gallery');

        Route::middleware('throttle:moderation')->group(function () {
            Route::get('/events/{event}/photos/{photo}/download', [GalleryController::class, 'download'])
                ->name('events.photos.download');

            Route::post('/events/{event}/exports', [ExportController::class, 'store'])
                ->name('events.exports.store');

            Route::get('/events/{event}/exports/{export}/download', [ExportController::class, 'download'])
                ->name('events.exports.download');
        });

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
