<?php

declare(strict_types=1);

use App\Domains\Providers\Enums\ProviderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول Domain المزودين — وثيقة 02 §7.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('base_url');
            $table->unsignedInteger('priority')->index();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_active')->default(false);
            $table->string('status')->default(ProviderStatus::Unknown->value);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_models', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('name');
            $table->string('display_name');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_default')->default(false);
            // التكلفة لكل مليون توكن بالدولار — أساس محرك التكلفة في المرحلة 2.
            $table->decimal('input_cost_per_million', 10, 4)->nullable();
            $table->decimal('output_cost_per_million', 10, 4)->nullable();
            $table->timestamps();

            $table->unique(['provider_id', 'name']);
        });

        Schema::create('ai_provider_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('label');
            // المفتاح مشفرًا عبر Eloquent encrypted cast — لا يخزن نصًا صريحًا أبدًا.
            $table->text('api_key');
            // آخر 4 أحرف فقط للعرض في الواجهة (وثيقة 02 §12).
            $table->string('key_hint', 8);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_provider_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->boolean('healthy');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('checked_at')->useCurrent();

            $table->index(['provider_id', 'checked_at']);
        });

        Schema::create('ai_failover_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('from_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->foreignId('to_provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('reason');
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_failover_events');
        Schema::dropIfExists('ai_provider_health_checks');
        Schema::dropIfExists('ai_provider_credentials');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
    }
};
