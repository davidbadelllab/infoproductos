<?php

use App\Http\Controllers\FacebookAdsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = auth()->user();

        // Obtener estadísticas del usuario
        $totalSearches = \App\Models\AdSearch::where('user_id', $user->id)->count();
        $totalWinners = \App\Models\FacebookAd::whereHas('adSearch', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->where('is_winner', true)->count();

        $totalPotential = \App\Models\FacebookAd::whereHas('adSearch', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->where('is_potential', true)->count();

        return Inertia::render('dashboard', [
            'stats' => [
                'total_searches' => $totalSearches,
                'total_winners' => $totalWinners,
                'total_potential' => $totalPotential,
            ],
        ]);
    })->name('dashboard');

    // Rutas de usuarios con permisos
    Route::middleware(['permission:view-users'])->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
    });

    Route::middleware(['permission:create-users'])->group(function () {
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
    });

    Route::middleware(['permission:view-users'])->group(function () {
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    });

    Route::middleware(['permission:edit-users'])->group(function () {
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::middleware(['permission:delete-users'])->group(function () {
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // Rutas de Facebook Ads Scraper
    Route::prefix('facebook-ads')->name('facebook-ads.')->group(function () {
        Route::middleware(['permission:view-facebook-ads'])->group(function () {
            Route::get('/', [FacebookAdsController::class, 'index'])->name('index');
            Route::get('/stats', [FacebookAdsController::class, 'stats'])->name('stats');
            Route::get('/{id}', [FacebookAdsController::class, 'show'])->name('show');
            Route::get('/{searchId}/ad/{adId}', [FacebookAdsController::class, 'showAd'])->name('show-ad');
        });

        Route::middleware(['permission:create-facebook-ads'])->group(function () {
            Route::post('/search', [FacebookAdsController::class, 'search'])->name('search');
            Route::post('/generate', [FacebookAdsController::class, 'generateCopy'])->name('generate');
            Route::post('/regenerate-image', [FacebookAdsController::class, 'regenerateImage'])->name('regenerate-image');
            Route::post('/generate-video', [FacebookAdsController::class, 'generateVideo'])->name('generate-video');
        });

        Route::middleware(['permission:export-facebook-ads'])->group(function () {
            Route::get('/{id}/export', [FacebookAdsController::class, 'export'])->name('export');
        });
    });

    // Rutas de Anuncios Clonados
    Route::prefix('cloned-ads')->name('cloned-ads.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ClonedAdsController::class, 'index'])->name('index');
        Route::get('/{uuid}', [\App\Http\Controllers\ClonedAdsController::class, 'show'])->name('show');
        Route::put('/{uuid}', [\App\Http\Controllers\ClonedAdsController::class, 'update'])->name('update');
        Route::post('/{uuid}/update-image', [\App\Http\Controllers\ClonedAdsController::class, 'updateImage'])->name('update-image');
        Route::post('/{uuid}/update-video', [\App\Http\Controllers\ClonedAdsController::class, 'updateVideo'])->name('update-video');
        Route::post('/{uuid}/generate-video', [\App\Http\Controllers\ClonedAdsController::class, 'generateVideo'])->name('generate-video');
        Route::delete('/{uuid}', [\App\Http\Controllers\ClonedAdsController::class, 'destroy'])->name('destroy');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
