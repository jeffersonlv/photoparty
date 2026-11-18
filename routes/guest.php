<?php

use App\Http\Controllers\Guest\GuestEventController;
use App\Http\Controllers\Guest\GuestLocationController;
use App\Http\Controllers\Guest\GuestMyPhotosController;
use App\Http\Controllers\Guest\GuestPhotoController;
use App\Http\Controllers\MediaController;
use App\Http\Middleware\GuardMediaNotFound;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulos 3-4: convidado e mídia (carregado com o grupo "web" por bootstrap/app.php)
|--------------------------------------------------------------------------
|
| Convidado: o evento é localizado por slug + hash do token (?t=). account_id e
| event_id saem do evento, nunca do request. Token inválido => 404.
|
| Mídia: fotos nunca ficam em public/. media.show exige login + PhotoPolicy;
| media.signed é para o telão (URL assinada relativa, 10 min: Photo::signedMediaUrl()).
|
*/

$uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
$slug = '[a-z0-9-]{1,80}';

Route::get('/e/{slug}', [GuestEventController::class, 'show'])
    ->where('slug', $slug)
    ->middleware('throttle:guest-page')
    ->name('guest.show');

Route::post('/e/{slug}/photos', [GuestPhotoController::class, 'store'])
    ->where('slug', $slug)
    ->middleware('throttle:guest-upload')
    ->name('guest.photos.store');

// "Envio só no local do evento": confere a posição (nunca gravada) e dá o passe de local da sessão.
Route::post('/e/{slug}/location', [GuestLocationController::class, 'store'])
    ->where('slug', $slug)
    ->middleware('throttle:guest-location')
    ->name('guest.location');

// "Minhas fotos" (LGPD/GDPR: acesso, portabilidade e eliminação): token do evento + cookie do
// aparelho; foto de outro aparelho/evento => 404; só enquanto o evento está ao vivo.
Route::delete('/e/{slug}/photos/{photo}', [GuestPhotoController::class, 'destroy'])
    ->where(['slug' => $slug, 'photo' => $uuid])
    ->middleware('throttle:guest-mine')
    ->name('guest.photos.destroy');

Route::get('/e/{slug}/my-photos', [GuestMyPhotosController::class, 'index'])
    ->where('slug', $slug)
    ->middleware('throttle:guest-mine')
    ->name('guest.mine.index');

Route::get('/e/{slug}/my-photos/{photo}/thumb', [GuestMyPhotosController::class, 'thumb'])
    ->where(['slug' => $slug, 'photo' => $uuid])
    ->middleware('throttle:guest-mine')
    ->name('guest.mine.thumb');

Route::get('/e/{slug}/my-photos/{photo}/download', [GuestMyPhotosController::class, 'download'])
    ->where(['slug' => $slug, 'photo' => $uuid])
    ->middleware('throttle:guest-mine')
    ->name('guest.mine.download');

Route::get('/media/{photo}/{variant}', [MediaController::class, 'show'])
    ->where(['photo' => $uuid, 'variant' => 'thumb|original'])
    ->middleware(['auth', GuardMediaNotFound::class, 'throttle:media'])
    ->name('media.show');

Route::get('/media/signed/{photo}/{variant}', [MediaController::class, 'signed'])
    ->where(['photo' => $uuid, 'variant' => 'thumb|original'])
    ->middleware([GuardMediaNotFound::class, 'signed:relative', 'throttle:media'])
    ->name('media.signed');
