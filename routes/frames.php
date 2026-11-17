<?php

use App\Http\Controllers\Frames\FrameLibraryController;
use App\Http\Controllers\Guest\GuestFrameController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo 8: molduras (carregado com o grupo "web" por bootstrap/app.php)
|--------------------------------------------------------------------------
|
| Prefixo /frame-library (e não /frames): public/frames/ é a pasta dos modelos prontos,
| e no Apache/servidor embutido um diretório real com o mesmo nome da rota a encobriria.
|
| Organizador: auth + verified + account; {frame} pelo uuid com o global scope da conta
| (outra conta => 404) + FramePolicy. Envio e ativação exigem plano pago (403 amigável).
|
| Convidado: config e PNG das molduras personalizadas ativas no evento, autorizados pelo
| token de upload do evento (?t=), como o envio de fotos.
|
*/

$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

Route::middleware(['auth', 'verified', 'account'])->scopeBindings()->group(function () use ($uuid) {
    Route::get('/frame-library', [FrameLibraryController::class, 'index'])->name('frames.index');

    Route::post('/frame-library', [FrameLibraryController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('frames.store');

    Route::get('/frame-library/guide/{orientation}.png', [FrameLibraryController::class, 'guide'])
        ->where('orientation', 'portrait|landscape')
        ->middleware('throttle:30,1')
        ->name('frames.guide');

    Route::get('/frame-library/{frame}/{orientation}.png', [FrameLibraryController::class, 'image'])
        ->where(['frame' => $uuid, 'orientation' => 'portrait|landscape'])
        ->middleware('throttle:media')
        ->name('frames.image');

    Route::put('/frame-library/{frame}/events', [FrameLibraryController::class, 'updateEvents'])
        ->where('frame', $uuid)
        ->name('frames.events.update');

    Route::delete('/frame-library/{frame}', [FrameLibraryController::class, 'destroy'])
        ->where('frame', $uuid)
        ->name('frames.destroy');
});

Route::get('/e/{slug}/frames/{frame}/{file}', [GuestFrameController::class, 'show'])
    ->where(['slug' => '[a-z0-9-]{1,80}', 'frame' => $uuid, 'file' => 'config\.json|portrait\.png|landscape\.png'])
    ->middleware('throttle:media')
    ->name('guest.frames.asset');
