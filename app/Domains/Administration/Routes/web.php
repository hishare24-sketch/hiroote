<?php

declare(strict_types=1);

use App\Domains\Administration\Http\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function (): void {
    Route::get('/audit', [AuditLogController::class, 'index'])
        ->middleware('permission:audit.view')
        ->name('audit.index');
});
