<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PortalAuthController;
use App\Http\Middleware\ClientPortalMiddleware;

Route::prefix('portal')->name('portal.')->group(function () {
    // Magic link login route
    Route::get('/login/{client}', [PortalAuthController::class, 'login'])->name('login')->middleware('signed');
    
    // Auth route to request a magic link (optional, if we want them to enter email)
    Route::get('/request-link', [PortalAuthController::class, 'showRequestForm'])->name('request-link');
    Route::post('/request-link', [PortalAuthController::class, 'sendLink'])->name('send-link');
    Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');

    // Protected Portal Routes
    Route::middleware(['auth:client'])->group(function () {
        Route::view('/dashboard', 'portal.dashboard')->name('dashboard');
        Route::view('/quotes', 'portal.quotes')->name('quotes');
        Route::view('/invoices', 'portal.invoices')->name('invoices');
    });
});
