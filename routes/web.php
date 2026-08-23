<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminBarcodeLookupController;
use App\Http\Controllers\AdminMetadataLookupController;
use App\Http\Controllers\AdminCollectionController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLoanController;
use App\Http\Controllers\AdminSettingsController;
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
    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/library', [AdminSettingsController::class, 'updateLibrary'])->name('settings.library.update');
    Route::put('/settings/external-services/{service}', [AdminSettingsController::class, 'updateExternalService'])->name('settings.external-services.update');
    Route::post('/settings/external-services/{service}/test', [AdminSettingsController::class, 'testExternalService'])->name('settings.external-services.test');
    Route::get('/loans', [AdminLoanController::class, 'index'])->name('loans.index');
    Route::post('/loans', [AdminLoanController::class, 'store'])->name('loans.store');
    Route::patch('/loans/{loan}/return', [AdminLoanController::class, 'markReturned'])->whereNumber('loan')->name('loans.return');
    Route::get('/collection', [AdminCollectionController::class, 'index'])->name('collection.index');
    Route::get('/collection/create', [AdminCollectionController::class, 'create'])->name('collection.create');
    Route::post('/collection/barcode-lookup', AdminBarcodeLookupController::class)->name('collection.barcode-lookup');
    Route::post('/collection/metadata/search', [AdminMetadataLookupController::class, 'search'])->name('collection.metadata.search');
    Route::post('/collection/metadata/import', [AdminMetadataLookupController::class, 'import'])->name('collection.metadata.import');
    Route::post('/collection/metadata/barcode/lookup', [AdminMetadataLookupController::class, 'barcodeLookup'])->name('collection.metadata.barcode.lookup');
    Route::post('/collection/metadata/tmdb/search', [AdminMetadataLookupController::class, 'tmdbSearch'])->name('collection.metadata.tmdb.search');
    Route::post('/collection/metadata/tmdb/import', [AdminMetadataLookupController::class, 'tmdbImport'])->name('collection.metadata.tmdb.import');
    Route::get('/collection/{item}', [AdminCollectionController::class, 'show'])->whereNumber('item')->name('collection.show');
    Route::post('/collection', [AdminCollectionController::class, 'store'])->name('collection.store');
    Route::get('/collection/{item}/edit', [AdminCollectionController::class, 'edit'])->name('collection.edit');
    Route::match(['put', 'patch'], '/collection/{item}', [AdminCollectionController::class, 'update'])->whereNumber('item')->name('collection.update');
    Route::delete('/collection/{item}', [AdminCollectionController::class, 'destroy'])->whereNumber('item')->name('collection.destroy');
});
Route::redirect('/admin/placeholder', '/admin/login')->name('admin.placeholder');
