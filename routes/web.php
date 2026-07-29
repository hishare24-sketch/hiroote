<?php

declare(strict_types=1);

use App\Domains\Analytics\Http\UsageController;
use App\Domains\Assistants\Http\AssistantsController;
use App\Domains\Assistants\Http\SectionsController;
use App\Domains\Conversations\Http\ConversationsController;
use App\Domains\Conversations\Http\EscalationsController;
use App\Domains\Projects\Http\ProjectsController;
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

    Route::middleware('permission:project.view')->group(function (): void {
        Route::get('/projects', [ProjectsController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectsController::class, 'store'])->name('projects.store');
        Route::put('/projects/{project}', [ProjectsController::class, 'update'])
            ->name('projects.update');
        Route::post('/projects/{project}/members', [ProjectsController::class, 'addMember'])
            ->name('projects.members.add');
        Route::delete('/projects/{project}/members/{user}', [ProjectsController::class, 'removeMember'])
            ->name('projects.members.remove');
    });

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

    Route::middleware('permission:assistants.view')->group(function (): void {
        Route::get('/assistants', [AssistantsController::class, 'index'])->name('assistants.index');
    });

    Route::middleware('permission:assistants.manage')->group(function (): void {
        Route::put('/assistants/levels/{level}', [AssistantsController::class, 'updateLevel'])
            ->name('assistants.levels.update');
        Route::put('/assistants/profile', [AssistantsController::class, 'updateProfile'])
            ->name('assistants.profile.update');
        Route::post('/assistants/functions', [AssistantsController::class, 'toggleFunction'])
            ->name('assistants.functions.toggle');
    });

    Route::get('/integrations', [SectionsController::class, 'index'])
        ->middleware('permission:integrations.view')
        ->name('integrations.index');

    Route::middleware('permission:integrations.manage')->group(function (): void {
        Route::post('/integrations/sections', [SectionsController::class, 'store'])
            ->name('integrations.sections.store');
        Route::put('/integrations/sections/{section}', [SectionsController::class, 'update'])
            ->name('integrations.sections.update');
        Route::delete('/integrations/sections/{section}', [SectionsController::class, 'destroy'])
            ->name('integrations.sections.destroy');
        Route::post('/integrations/sections/{section}/toggle', [SectionsController::class, 'toggle'])
            ->name('integrations.sections.toggle');
    });

    // الشاشات المخططة لمراحل لاحقة — تعرض نطاقها بدل خطأ 404، ويستبدل كل
    // مسار بمتحكمه الحقيقي عند تنفيذ مرحلته.
    $planned = [
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
