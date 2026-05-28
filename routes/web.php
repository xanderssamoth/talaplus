<?php

use App\Http\Controllers\AdminResourceController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['fr', 'en'], true), 404);
    session(['locale' => $locale]);

    return back();
})->name('lang.switch');

Route::get('/dashboard', [AdminResourceController::class, 'dashboard'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/account', [AdminResourceController::class, 'account'])->name('account');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    foreach (['categories', 'roles', 'reasons', 'pricings', 'abouts', 'videos', 'products', 'app-infos', 'users', 'messages', 'notifications'] as $resource) {
        Route::get("/{$resource}", [AdminResourceController::class, 'index'])->defaults('resource', $resource)->name("{$resource}.index");
        Route::get("/{$resource}/data", [AdminResourceController::class, 'list'])->defaults('resource', $resource)->name("{$resource}.data");
        Route::get("/{$resource}/{id}", [AdminResourceController::class, 'show'])->defaults('resource', $resource)->whereNumber('id')->name("{$resource}.show");
        Route::post("/{$resource}", [AdminResourceController::class, 'store'])->defaults('resource', $resource)->name("{$resource}.store");
        if (in_array($resource, ['videos', 'products'], true)) {
            Route::patch("/{$resource}/{id}/shared", [AdminResourceController::class, 'toggleShared'])->defaults('resource', $resource)->whereNumber('id')->name("{$resource}.shared");
        }
        if ($resource === 'notifications') {
            Route::patch("/{$resource}/{id}/read", [AdminResourceController::class, 'markNotificationAsRead'])->whereNumber('id')->name("{$resource}.read");
        }
        if ($resource === 'users') {
            Route::patch("/{$resource}/{id}/status", [AdminResourceController::class, 'updateUserStatus'])->whereNumber('id')->name("{$resource}.status");
        }
        Route::put("/{$resource}/{id}", [AdminResourceController::class, 'update'])->defaults('resource', $resource)->whereNumber('id')->name("{$resource}.update");
        Route::delete("/{$resource}/{id}", [AdminResourceController::class, 'destroy'])->defaults('resource', $resource)->whereNumber('id')->name("{$resource}.destroy");
    }

    Route::get('/admin/{resource}', [AdminResourceController::class, 'index'])->name('admin.resources.index');
    Route::get('/admin/{resource}/data', [AdminResourceController::class, 'list'])->name('admin.resources.list');
    Route::post('/admin/{resource}', [AdminResourceController::class, 'store'])->name('admin.resources.store');
});

require __DIR__.'/auth.php';
