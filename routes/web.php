<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminCollectionController;
use App\Http\Controllers\AdminDashboardController;
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

Route::get('/admin/login', [AdminAuthController::class, 'show'])->name('login');
Route::post('/admin/login', [AdminAuthController::class, 'store'])->name('admin.login');
Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])->name('admin.logout');
Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin');
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/collection', [AdminCollectionController::class, 'index'])->name('collection.index');
    Route::get('/collection/create', [AdminCollectionController::class, 'create'])->name('collection.create');
    Route::post('/collection', [AdminCollectionController::class, 'store'])->name('collection.store');
    Route::get('/collection/{item}/edit', [AdminCollectionController::class, 'edit'])->name('collection.edit');
    Route::match(['put', 'patch'], '/collection/{item}', [AdminCollectionController::class, 'update'])->name('collection.update');
    Route::delete('/collection/{item}', [AdminCollectionController::class, 'destroy'])->name('collection.destroy');
});
Route::redirect('/admin/placeholder', '/admin/login')->name('admin.placeholder');
