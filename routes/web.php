<?php

declare(strict_types=1);

use App\Domains\Alerts\Http\AlertsController;
use App\Domains\Analytics\Http\UsageController;
use App\Domains\Assistants\Http\AssistantsController;
use App\Domains\Assistants\Http\SectionsController;
use App\Domains\Conversations\Http\ConversationsController;
use App\Domains\Conversations\Http\EscalationsController;
use App\Domains\Integrations\Http\BridgeController;
use App\Domains\Knowledge\Http\KnowledgeController;
use App\Domains\Projects\Http\ProjectsController;
use App\Domains\Projects\Http\SwitchProjectController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\HelpController;
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

    // شرح الشاشة الحالية — بلا صلاحية خاصة: من يرى شاشةً يحقّ له فهمها.
    Route::get('/help/topic', HelpController::class)->name('help.topic');

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
        Route::post('/projects/{project}/keys', [ProjectsController::class, 'issueKey'])
            ->name('projects.keys.issue');
        Route::delete('/projects/{project}/keys/{key}', [ProjectsController::class, 'revokeKey'])
            ->name('projects.keys.revoke');
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

    Route::get('/bridge', [BridgeController::class, 'index'])
        ->middleware('permission:integrations.view')
        ->name('bridge.index');

    Route::post('/bridge', [BridgeController::class, 'save'])
        ->middleware('permission:integrations.manage')
        ->name('bridge.save');

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

    Route::middleware('permission:knowledge.view')->group(function (): void {
        Route::get('/knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
        Route::get('/knowledge/sections/{section}', [KnowledgeController::class, 'show'])
            ->name('knowledge.sections.show');
        Route::get('/knowledge/items/{item}/versions', [KnowledgeController::class, 'versions'])
            ->name('knowledge.items.versions');
    });

    Route::middleware('permission:knowledge.manage')->group(function (): void {
        Route::post('/knowledge/sections/{section}/items', [KnowledgeController::class, 'storeItem'])
            ->name('knowledge.items.store');
        Route::put('/knowledge/items/{item}', [KnowledgeController::class, 'updateItem'])
            ->name('knowledge.items.update');
        Route::post('/knowledge/items/{item}/versions/{version}/restore', [KnowledgeController::class, 'restore'])
            ->name('knowledge.versions.restore');
        Route::post('/knowledge/feedback/{feedback}/verify', [KnowledgeController::class, 'verifyFeedback'])
            ->name('knowledge.feedback.verify');
        Route::post('/knowledge/feedback/{feedback}/close', [KnowledgeController::class, 'closeFeedback'])
            ->name('knowledge.feedback.close');
        Route::post('/knowledge/feedback/{feedback}/assign', [KnowledgeController::class, 'assignFeedback'])
            ->name('knowledge.feedback.assign');

        Route::post('/knowledge/sections/{section}/screens', [KnowledgeController::class, 'storeScreen'])
            ->name('knowledge.screens.store');
        // POST لا PUT: رفع الملفات يحتاج multipart، وهو ما لا يحمله PUT في المتصفح.
        Route::post('/knowledge/screens/{screen}', [KnowledgeController::class, 'updateScreen'])
            ->name('knowledge.screens.update');
        Route::delete('/knowledge/screens/{screen}', [KnowledgeController::class, 'destroyScreen'])
            ->name('knowledge.screens.destroy');
    });

    Route::middleware('permission:alerts.view')->group(function (): void {
        Route::get('/alerts', [AlertsController::class, 'index'])->name('alerts.index');
    });

    Route::middleware('permission:alerts.manage')->group(function (): void {
        Route::post('/alerts', [AlertsController::class, 'store'])->name('alerts.store');
        Route::put('/alerts/{rule}', [AlertsController::class, 'update'])->name('alerts.update');
        Route::delete('/alerts/{rule}', [AlertsController::class, 'destroy'])->name('alerts.destroy');
        Route::post('/alerts/{rule}/test', [AlertsController::class, 'test'])->name('alerts.test');
        Route::post('/alerts/evaluate', [AlertsController::class, 'evaluate'])->name('alerts.evaluate');
        Route::post('/alerts/events/{event}', [AlertsController::class, 'resolveEvent'])
            ->name('alerts.events.resolve');
    });

    // الشاشات المخططة لمراحل لاحقة — تعرض نطاقها بدل خطأ 404، ويستبدل كل
    // مسار بمتحكمه الحقيقي عند تنفيذ مرحلته.
    $planned = [
        'users' => 'users.view',
    ];

    foreach ($planned as $screen => $permission) {
        Route::get("/{$screen}", PlannedScreenController::class)
            ->defaults('screen', $screen)
            ->middleware("permission:{$permission}")
            ->name("{$screen}.index");
    }
});
