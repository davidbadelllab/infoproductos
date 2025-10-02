<?php

use App\Http\Controllers\Settings\ApifyCredentialsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\GeminiCredentialsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/credentials/apify', [ApifyCredentialsController::class, 'edit'])
        ->name('settings.credentials.apify.edit');

    Route::put('settings/credentials/apify', [ApifyCredentialsController::class, 'update'])
        ->name('settings.credentials.apify.update');

    Route::get('settings/credentials/gemini', [GeminiCredentialsController::class, 'edit'])
        ->name('settings.credentials.gemini.edit');

    Route::put('settings/credentials/gemini', [GeminiCredentialsController::class, 'update'])
        ->name('settings.credentials.gemini.update');
});
