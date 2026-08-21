<?php

use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\LocaleController;
use App\Http\Middleware\SetLocale;
use App\Livewire\AdminUi;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/vehicles');
Route::get('serviceworker.js', function () {
    $manifestPath = public_path('build/manifest.json');
    $workerPath = resource_path('views/serviceworker.blade.php');
    $version = substr(hash(
        'sha256',
        (is_file($manifestPath) ? file_get_contents($manifestPath) : '').file_get_contents($workerPath),
    ), 0, 12);

    return response()
        ->view('serviceworker', compact('version'))
        ->header('Content-Type', 'application/javascript; charset=UTF-8')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
        ->header('Service-Worker-Allowed', '/');
})->name('serviceworker');
Route::view('offline', 'offline')
    ->middleware(SetLocale::class)
    ->name('offline');

Route::get('locale/{locale}', LocaleController::class)
    ->whereIn('locale', ['lv', 'ru'])
    ->name('locale.switch');

Route::middleware(['auth', 'verified', SetLocale::class])->group(function () {
    Route::redirect('dashboard', '/vehicles')->name('dashboard');

    Route::get('vehicles', AdminUi::class)->defaults('section', 'vehicles')->defaults('mode', 'index')->name('vehicles.index');
    Route::get('vehicles/create', AdminUi::class)->defaults('section', 'vehicles')->defaults('mode', 'create')->name('vehicles.create');
    Route::get('vehicles/{recordId}', AdminUi::class)->whereNumber('recordId')->defaults('section', 'vehicles')->defaults('mode', 'show')->name('vehicles.show');
    Route::get('vehicles/{recordId}/edit', AdminUi::class)->whereNumber('recordId')->defaults('section', 'vehicles')->defaults('mode', 'edit')->name('vehicles.edit');

    foreach (['templates', 'plans', 'service-records', 'defects', 'expenses', 'documents', 'reports', 'notifications', 'users', 'audit'] as $legacySection) {
        Route::get($legacySection.'/{path?}', fn () => redirect()->route('vehicles.index'))
            ->where('path', '.*');
    }

    Route::get('attachments/{attachment}/download', AttachmentDownloadController::class)
        ->whereNumber('attachment')
        ->name('attachments.download');
    Route::view('profile', 'profile')->name('profile');
});

Route::middleware(SetLocale::class)->group(function () {
    require __DIR__.'/auth.php';
});
