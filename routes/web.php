<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PlannedScreenController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', OverviewController::class)
        ->middleware('permission:overview.view')
        ->name('overview');

    // الشاشات المخططة لمراحل لاحقة — تعرض نطاقها بدل خطأ 404، ويستبدل كل
    // مسار بمتحكمه الحقيقي عند تنفيذ مرحلته.
    $planned = [
        'conversations' => 'conversations.view',
        'usage' => 'usage.view',
        'escalations' => 'escalations.view',
        'assistants' => 'assistants.view',
        'integrations' => 'integrations.view',
        'knowledge' => 'knowledge.view',
        'alerts' => 'alerts.view',
        'users' => 'users.view',
    ];

    foreach ($planned as $screen => $permission) {
        Route::get("/{$screen}", PlannedScreenController::class)
            ->defaults('screen', $screen)
            ->middleware("permission:{$permission}")
            ->name("{$screen}.index");
    }
});
