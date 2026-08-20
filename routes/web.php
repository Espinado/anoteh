<?php

use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ReportDownloadController;
use App\Http\Middleware\SetLocale;
use App\Livewire\AdminUi;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('locale/{locale}', LocaleController::class)
    ->whereIn('locale', ['lv', 'ru'])
    ->name('locale.switch');

Route::middleware(['auth', 'verified', SetLocale::class])->group(function () {
    Route::get('dashboard', AdminUi::class)->defaults('section', 'dashboard')->defaults('mode', 'index')->name('dashboard');

    $resources = [
        'vehicles', 'templates', 'plans', 'service-records', 'defects',
        'expenses', 'documents', 'users',
    ];

    foreach ($resources as $resource) {
        Route::get($resource, AdminUi::class)->defaults('section', $resource)->defaults('mode', 'index')->name($resource.'.index');
        Route::get($resource.'/create', AdminUi::class)->defaults('section', $resource)->defaults('mode', 'create')->name($resource.'.create');
        Route::get($resource.'/{recordId}', AdminUi::class)->whereNumber('recordId')->defaults('section', $resource)->defaults('mode', 'show')->name($resource.'.show');
        Route::get($resource.'/{recordId}/edit', AdminUi::class)->whereNumber('recordId')->defaults('section', $resource)->defaults('mode', 'edit')->name($resource.'.edit');
    }

    Route::get('reports', AdminUi::class)->defaults('section', 'reports')->defaults('mode', 'index')->name('reports.index');
    Route::get('reports/download', ReportDownloadController::class)->name('reports.download');
    Route::get('reports/print', [ReportDownloadController::class, 'print'])->name('reports.print');
    Route::get('audit', AdminUi::class)->defaults('section', 'audit')->defaults('mode', 'index')->name('audit.index');
    Route::get('attachments/{attachment}/download', AttachmentDownloadController::class)
        ->whereNumber('attachment')
        ->name('attachments.download');
    Route::get('notifications', AdminUi::class)->defaults('section', 'notifications')->defaults('mode', 'index')->name('notifications.index');
    Route::view('profile', 'profile')->name('profile');
});

Route::middleware(SetLocale::class)->group(function () {
    require __DIR__.'/auth.php';
});
