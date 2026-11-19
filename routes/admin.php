<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FrameController;
use App\Http\Controllers\Admin\HomeItemController;
use App\Http\Controllers\Admin\HomeSectionController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\PrivacyController;
use App\Http\Controllers\Admin\SiteSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Área do admin (/admin)
|--------------------------------------------------------------------------
|
| Ordem dos middlewares: auth, admin (não-admin => 404 mesmo sem e-mail
| verificado), verified. Toda ação grava em audit_logs (AuditLogger).
|
*/

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'verified'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    // Contas
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::put('/accounts/settings', [AccountController::class, 'updateSettings'])->name('accounts.settings');
    Route::get('/accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
    Route::put('/accounts/{account}/plan', [AccountController::class, 'updatePlan'])->name('accounts.plan');
    Route::put('/accounts/{account}/paid-until', [AccountController::class, 'updatePaidUntil'])->name('accounts.paid-until');
    Route::put('/accounts/{account}/status', [AccountController::class, 'updateStatus'])->name('accounts.status');
    Route::post('/accounts/{account}/extend-trial', [AccountController::class, 'extendTrial'])->name('accounts.extend-trial');

    // Planos
    Route::resource('plans', PlanController::class)->except('show');

    // Molduras do sistema
    Route::get('/frames', [FrameController::class, 'index'])->name('frames.index');
    Route::post('/frames/{frame}/toggle', [FrameController::class, 'toggle'])->name('frames.toggle');
    Route::post('/frames/{frame}/move', [FrameController::class, 'move'])->name('frames.move');

    // Auditoria
    Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');

    // Privacidade (LGPD/GDPR): solicitações de titulares e dados do operador/DPO
    Route::get('/privacy', [PrivacyController::class, 'index'])->name('privacy.requests.index');
    Route::put('/privacy/requests/{privacyRequest}', [PrivacyController::class, 'update'])->name('privacy.requests.update');
    Route::get('/privacy/settings', [PrivacyController::class, 'editSettings'])->name('privacy.settings.edit');
    Route::put('/privacy/settings', [PrivacyController::class, 'updateSettings'])->name('privacy.settings.update');

    // Página inicial
    Route::prefix('home')->name('home.')->group(function () {
        Route::get('/', [HomeSectionController::class, 'index'])->name('index');
        Route::get('/preview', [HomeSectionController::class, 'preview'])->name('preview');
        Route::get('/settings', [SiteSettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [SiteSettingController::class, 'update'])->name('settings.update');

        Route::scopeBindings()->group(function () {
            Route::get('/sections/{section}', [HomeSectionController::class, 'edit'])->name('sections.edit');
            Route::put('/sections/{section}', [HomeSectionController::class, 'update'])->name('sections.update');
            Route::post('/sections/{section}/toggle', [HomeSectionController::class, 'toggle'])->name('sections.toggle');
            Route::post('/sections/{section}/move', [HomeSectionController::class, 'move'])->name('sections.move');
            Route::match(['POST', 'PUT'], '/sections/{section}/preview', [HomeSectionController::class, 'previewSection'])->name('sections.preview');

            Route::get('/sections/{section}/items/create', [HomeItemController::class, 'create'])->name('items.create');
            Route::post('/sections/{section}/items', [HomeItemController::class, 'store'])->name('items.store');
            Route::get('/sections/{section}/items/{item}/edit', [HomeItemController::class, 'edit'])->name('items.edit');
            Route::put('/sections/{section}/items/{item}', [HomeItemController::class, 'update'])->name('items.update');
            Route::delete('/sections/{section}/items/{item}', [HomeItemController::class, 'destroy'])->name('items.destroy');
            Route::post('/sections/{section}/items/{item}/toggle', [HomeItemController::class, 'toggle'])->name('items.toggle');
            Route::post('/sections/{section}/items/{item}/move', [HomeItemController::class, 'move'])->name('items.move');
        });
    });
});
