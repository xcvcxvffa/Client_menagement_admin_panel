<?php

use Illuminate\Support\Facades\Route;

Route::get('/welcome', function () {
    return view('welcome');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// CRM & ERP Modules Placeholder Routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('leads', 'leads.index')->name('leads.index')->middleware('can:view leads');
    
    Route::view('clients', 'clients.index')->name('clients.index')->middleware('can:view clients');
    Route::view('clients/create', 'clients.create')->name('clients.create')->middleware('can:create clients');
    Route::get('clients/{client}', function (App\Models\Client $client) {
        return view('clients.show', ['client' => $client]);
    })->name('clients.show')->middleware('can:view clients');
    
    Route::view('projects', 'projects.index')->name('projects.index')->middleware('can:view projects');
    Route::get('projects/{project}', function (App\Models\Project $project) {
        return view('projects.show', ['project' => $project]);
    })->name('projects.show')->middleware('can:view projects');

    Route::view('retainers', 'retainers.index')->name('retainers.index')->middleware('can:view retainers');
    Route::get('retainers/{retainer}', function (App\Models\Retainer $retainer) {
        return view('retainers.show', ['retainer' => $retainer]);
    })->name('retainers.show')->middleware('can:view retainers');
    
    Route::view('payments', 'payments.index')->name('payments.index')->middleware('can:view payments');
    
    Route::view('tasks', 'tasks.index')->name('tasks.index')->middleware('can:view tasks');
    
    Route::view('messages', 'messages.index')->name('messages.index')->middleware('can:view messages');

    Route::view('quotes', 'quotes.index')->name('quotes.index')->middleware('can:view quotes');
    Route::view('quotes/create', 'quotes.create')->name('quotes.create')->middleware('can:create quotes');
    Route::get('quotes/{quote}', function (App\Models\Quote $quote) {
        return view('quotes.show', ['quote' => $quote]);
    })->name('quotes.show')->middleware('can:view quotes');
    Route::get('quotes/{quote}/pdf', [App\Http\Controllers\QuotePdfController::class, 'download'])->name('quotes.pdf')->middleware('can:export quotes');
    
    Route::view('billing', 'billing.index')->name('billing.index')->middleware('can:view invoices');
    Route::view('expenses', 'expenses.index')->name('expenses.index')->middleware('can:view expenses');
    
    Route::view('invoices', 'invoices.index')->name('invoices.index')->middleware('can:view invoices');
    Route::view('invoices/create', 'invoices.create')->name('invoices.create')->middleware('can:create invoices');
    Route::get('invoices/{invoice}', function (App\Models\Invoice $invoice) {
        return view('invoices.show', ['invoice' => $invoice]);
    })->name('invoices.show')->middleware('can:view invoices');
    Route::get('invoices/{invoice}/pdf', [App\Http\Controllers\InvoicePdfController::class, 'download'])->name('invoices.pdf');
    

    Route::get('/files', \App\Livewire\FileManagement\FileManager::class)->name('files.index');
    Route::get('/files/download/{id}', [\App\Http\Controllers\FileDownloadController::class, 'download'])->name('files.download');

    Route::view('/team', 'team.index')->name('team.index')->middleware('can:view team');
    
    // Content Management
    Route::get('/content', function () { return view('contents.index'); })->name('contents.index')->middleware('can:view content');
    Route::get('/content/{content}', function ($id) { return view('contents.show', ['id' => $id]); })->name('contents.show')->middleware('can:view content');
    
    Route::prefix('settings')->group(function () {
        Route::view('/', 'settings.index')->name('settings.index')->middleware('can:view settings');
        Route::get('/billing', function () { return view('settings.billing'); })->name('settings.billing')->middleware('can:view settings');
        Route::view('/roles', 'settings.roles')->name('settings.roles')->middleware('can:view roles');
        Route::view('/permissions', 'settings.permissions')->name('settings.permissions')->middleware('can:view permissions');
        Route::view('/users', 'settings.users')->name('settings.users')->middleware('can:view team');
    });
});

require __DIR__.'/auth.php';
