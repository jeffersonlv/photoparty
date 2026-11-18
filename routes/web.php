<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

// A rota '/' (nome 'home') vive em routes/home.php.

Route::get('/locale/{locale}', LocaleController::class)->name('locale');

/*
|--------------------------------------------------------------------------
| Autenticação
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:password-reset')
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

/*
|--------------------------------------------------------------------------
| Área do organizador
|--------------------------------------------------------------------------
|
| auth + e-mail verificado + usuário ligado a uma conta. {event} é resolvido
| pelo uuid com o global scope da conta (outra conta => 404) e scopeBindings()
| garante que recursos aninhados (fotos, exportações) pertençam ao evento.
|
*/

Route::middleware(['auth', 'verified', 'account'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Minha conta (LGPD/GDPR do organizador): exportar dados e excluir a conta.
    Route::get('/account', [AccountController::class, 'show'])->name('account.show');
    Route::get('/account/export', [AccountController::class, 'export'])
        ->middleware('throttle:6,1')
        ->name('account.export');
    Route::delete('/account', [AccountController::class, 'destroy'])
        ->middleware('throttle:5,1')
        ->name('account.destroy');

    Route::scopeBindings()->group(function () {
        Route::get('/events', [EventController::class, 'index'])->name('events.index');

        Route::middleware('account.can-manage-events')->group(function () {
            Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
            Route::post('/events', [EventController::class, 'store'])->name('events.store');
            Route::post('/events/{event}/publish', [EventController::class, 'publish'])->name('events.publish');
        });

        Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
        Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
        Route::post('/events/{event}/close', [EventController::class, 'close'])->name('events.close');
        Route::post('/events/{event}/screen-token', [EventController::class, 'regenerateScreenToken'])->name('events.screen-token');
        Route::get('/events/{event}/qr.{format}', [EventController::class, 'qr'])
            ->whereIn('format', ['png', 'svg'])
            ->name('events.qr');

        // Módulo 6 (moderação/galeria):
        // Route::get('/events/{event}/photos', ...)->name('events.photos.index');
        // Route::get('/events/{event}/photos/{photo}', ...)  // scopeBindings: foto precisa ser do evento
        // Módulo 7 (exportação):
        // Route::post('/events/{event}/exports', ...)->name('events.exports.store');
        // Route::get('/events/{event}/exports/{export}', ...)->name('events.exports.show');
    });

    // Módulo 4: Route::get('/media/{photo}/{variant}', ...) com policy + URL assinada.
});

/*
|--------------------------------------------------------------------------
| Rotas públicas por token (outros módulos)
|--------------------------------------------------------------------------
|
| Módulo 3/4 — convidado: GET /e/{slug}?t={upload_token}, POST /e/{slug}/photos
|   O evento é localizado por Event::whereUploadToken($t); account_id/event_id
|   saem do evento, nunca do request.
| Módulo 5 — telão: GET /screen/{token}, GET /screen/{token}/photos?after=...
|   Evento localizado por Event::whereScreenToken($token).
|
*/
