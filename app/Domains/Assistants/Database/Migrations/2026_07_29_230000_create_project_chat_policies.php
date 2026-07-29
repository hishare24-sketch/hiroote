<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حوكمة الشات لكل مشروع — ما يسمح به المالك، لا رسائلُ من كتبها.
 *
 * لا جدول رسائل هنا عمدًا: رسائل مستخدمي المشروع تبقى في مشروعه (وثيقة 01 §6،
 * وقاعدة CLAUDE.md رقم ١). ما تملكه هذه اللوحة هو **الإذن**: أي أنواع القنوات
 * تُفتح، وفي أي دوائر، وهل يشارك المساعد فيها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_chat_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();

            $table->boolean('is_enabled')->default(false);

            // أنواع القنوات المسموحة — assistant|direct|group|support
            $table->jsonb('channel_kinds')->default('["assistant"]');
            // الدوائر المسموحة — project|subscriber|platform|support
            $table->jsonb('scopes')->default('["project"]');

            $table->boolean('assistant_participates')->default(true);
            $table->boolean('attachments_allowed')->default(false);
            // صفر = بلا حدّ (ويُعرض «بلا حدّ» لا صفرًا)
            $table->unsignedSmallInteger('retention_days')->default(0);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_chat_policies');
    }
};
