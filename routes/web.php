<?php

declare(strict_types=1);

use App\Domains\Analytics\Http\UsageController;
use App\Domains\Conversations\Http\ConversationsController;
use App\Domains\Conversations\Http\EscalationsController;
use App\Domains\Projects\Http\SwitchProjectController;
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

    // التبديل بلا صلاحية خاصة: العضوية نفسها هي الإذن (ADR-0003 §4).
    Route::post('/projects/{project}/switch', SwitchProjectController::class)
        ->name('projects.switch');

    Route::get('/', OverviewController::class)
        ->middleware('permission:overview.view')
        ->name('overview');

    Route::middleware('permission:conversations.view')->group(function (): void {
        Route::get('/conversations', [ConversationsController::class, 'index'])->name('conversations.index');
        Route::get('/conversations/{conversation}', [ConversationsController::class, 'show'])
            ->name('conversations.show');
    });

    Route::get('/usage', UsageController::class)
        ->middleware('permission:usage.view')
        ->name('usage.index');

    Route::get('/escalations', EscalationsController::class)
        ->middleware('permission:escalations.view')
        ->name('escalations.index');

    // الشاشات المخططة لمراحل لاحقة — تعرض نطاقها بدل خطأ 404، ويستبدل كل
    // مسار بمتحكمه الحقيقي عند تنفيذ مرحلته.
    $planned = [
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
