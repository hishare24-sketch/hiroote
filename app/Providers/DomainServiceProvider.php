<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the domain modules described in وثيقة 02 §6.
 *
 * Each domain owns its own routes, migrations and translations; nothing here
 * reaches across a boundary. A new domain is added by listing it in
 * `self::DOMAINS` — no other file needs to change.
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * @var list<string>
     */
    public const DOMAINS = [
        // Projects أولًا: هجراته تنشئ الجدول الذي تشير إليه بقية المجالات.
        'Projects',
        'Assistants',
        'Providers',
        'Conversations',
        'Knowledge',
        'Integrations',
        'Analytics',
        'Alerts',
        'Administration',
    ];

    public function boot(): void
    {
        foreach (self::DOMAINS as $domain) {
            $base = app_path("Domains/{$domain}");

            if (is_dir("{$base}/Database/Migrations")) {
                $this->loadMigrationsFrom("{$base}/Database/Migrations");
            }

            $webRoutes = "{$base}/Routes/web.php";
            if (is_file($webRoutes)) {
                Route::middleware('web')->group($webRoutes);
            }

            $apiRoutes = "{$base}/Routes/api.php";
            if (is_file($apiRoutes)) {
                Route::middleware('api')->prefix('api/v1')->group($apiRoutes);
            }
        }
    }
}
