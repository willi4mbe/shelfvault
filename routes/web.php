<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminBarcodeLookupController;
use App\Http\Controllers\AdminCollectionController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminLoanController;
use App\Http\Controllers\AdminMetadataLookupController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\PublicLibraryController;
use Illuminate\Support\Facades\Route;

Route::redirect('/install', '/install.php');
Route::prefix('dev/install-preview')->name('install.')->group(function (): void {
    Route::get('/', [InstallController::class, 'preview'])->name('requirements');
    Route::post('/locale', [InstallController::class, 'previewLocale'])->name('locale');
    Route::get('/database', [InstallController::class, 'previewRedirect'])->name('database');
    Route::post('/database', [InstallController::class, 'previewRedirect'])->name('database.store');
    Route::post('/complete', [InstallController::class, 'previewRedirect'])->name('complete');
});

Route::get('/', [PublicLibraryController::class, 'home'])->name('library.home');
Route::redirect('/library', '/')->name('library.index');
Route::get('/storage/{path}', [InstallController::class, 'publicStorage'])
    ->where('path', '.*')
    ->name('storage.public');
Route::get('/library/search', [PublicLibraryController::class, 'search'])->name('library.search');
Route::get('/library/favorites', [PublicLibraryController::class, 'favorites'])->name('library.favorites');
Route::get('/library/loans', [PublicLibraryController::class, 'loans'])->name('library.loans');
Route::get('/library/recent', [PublicLibraryController::class, 'recent'])->name('library.recent');
Route::get('/library/genres', [PublicLibraryController::class, 'genres'])->name('library.genres');
Route::get('/library/years', [PublicLibraryController::class, 'years'])->name('library.years');
Route::get('/library/items/{item}', [PublicLibraryController::class, 'show'])->whereNumber('item')->name('library.items.show');
Route::get('/library/{type}', [PublicLibraryController::class, 'type'])->name('library.type');

Route::get('/admin/login', [AdminAuthController::class, 'show'])->name('login');
Route::post('/admin/login', [AdminAuthController::class, 'store'])->name('admin.login');
Route::post('/admin/logout', [AdminAuthController::class, 'destroy'])->name('admin.logout');
Route::get('/admin', [AdminDashboardController::class, 'index'])->name('admin');
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/settings', [AdminSettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
    Route::put('/settings/library', [AdminSettingsController::class, 'updateLibrary'])->name('settings.library.update');
    Route::post('/settings/updates/check', [AdminSettingsController::class, 'checkUpdates'])->name('settings.updates.check');
    Route::post('/settings/updates/install', [AdminSettingsController::class, 'installUpdate'])->name('settings.updates.install');
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
