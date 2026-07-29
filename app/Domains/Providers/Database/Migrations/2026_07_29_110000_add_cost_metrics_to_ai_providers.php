<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مقاييس بطاقة المزود النشط — وثيقة التصميم §8:
 * زمن الاستجابة، معدل الأخطاء، الرصيد المتبقي، معدل الاستهلاك.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table): void {
            // مبالغ نقدية — decimal لا float، فلا تتراكم أخطاء التقريب.
            $table->decimal('balance', 12, 2)->default(0);
            $table->decimal('burn_rate_per_minute', 10, 4)->default(0);
            $table->string('currency', 3)->default('SAR');

            // متوسطات متجددة تُحدَّث من الفحص الذاتي.
            $table->unsignedInteger('avg_latency_ms')->nullable();
            $table->decimal('error_rate', 5, 2)->default(0);
        });

        Schema::create('provider_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('enabled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_settings');

        Schema::table('ai_providers', function (Blueprint $table): void {
            $table->dropColumn([
                'balance',
                'burn_rate_per_minute',
                'currency',
                'avg_latency_ms',
                'error_rate',
            ]);
        });
    }
};
