<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

Route::prefix('install')->name('install.')->group(function (): void {
    Route::get('/', [InstallController::class, 'show'])->name('show');
    Route::post('/locale', [InstallController::class, 'locale'])->name('locale');
    Route::get('/database', [InstallController::class, 'database'])->name('database');
    Route::post('/database', [InstallController::class, 'storeDatabase'])->name('database.store');
    Route::get('/admin', [InstallController::class, 'admin'])->name('admin');
    Route::post('/complete', [InstallController::class, 'complete'])->name('complete');
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin', function () {
    return view('admin.placeholder');
})->name('admin.placeholder');
