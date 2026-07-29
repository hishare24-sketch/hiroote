<?php

declare(strict_types=1);

use App\Domains\Providers\Http\ProvidersController;
use App\Domains\Providers\Http\ProviderSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::post('/settings/toggle', [ProviderSettingsController::class, 'toggle'])
        ->middleware('permission:maintenance.toggle')
        ->name('settings.toggle');

    Route::prefix('providers')->name('providers.')->group(function (): void {
        Route::get('/', [ProvidersController::class, 'index'])
            ->middleware('permission:providers.view')
            ->name('index');

        Route::post('/reorder', [ProvidersController::class, 'reorder'])
            ->middleware('permission:providers.manage')
            ->name('reorder');

        Route::post('/{provider}/toggle', [ProvidersController::class, 'toggle'])
            ->middleware('permission:providers.manage')
            ->name('toggle');

        Route::post('/{provider}/check', [ProvidersController::class, 'check'])
            ->middleware('permission:providers.manage')
            ->name('check');

        Route::post('/{provider}/activate', [ProvidersController::class, 'activate'])
            ->middleware('permission:providers.failover')
            ->name('activate');

        Route::post('/{provider}/credentials', [ProvidersController::class, 'storeCredential'])
            ->middleware('permission:providers.manage_credentials')
            ->name('credentials.store');

        Route::delete('/{provider}/credentials/{credential}', [ProvidersController::class, 'revokeCredential'])
            ->middleware('permission:providers.manage_credentials')
            ->name('credentials.revoke');
    });
});
