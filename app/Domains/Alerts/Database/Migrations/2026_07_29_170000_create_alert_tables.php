<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التنبيهات — وثيقة 06 §11 و وثيقة 02 §7.
 *
 * القاعدة تصف ما يُراقَب، والحدث يسجّل مرة تجاوزٍ واحدة، والإيصال يسجّل محاولة
 * وصول واحدة إلى مستلم واحد. فصلها ثلاثةً يجعل «فُعِّلت ٤ مرات ووصل إشعاران»
 * جملةً يمكن إثباتها من الجداول لا تقديرًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('description')->nullable();

            $table->string('metric');
            $table->string('comparison');
            $table->decimal('threshold', 12, 2);

            // صفر للمؤشرات اللحظية (الرصيد، معدل الأخطاء) — لا نافذة لها.
            $table->unsignedInteger('window_minutes')->default(1440);

            $table->string('severity')->default('warning');
            $table->boolean('is_enabled')->default(true);

            // التهدئة تمنع تكرار الإشعار عن السبب نفسه قبل انقضائها.
            $table->unsignedInteger('cooldown_minutes')->default(60);

            $table->string('auto_action')->default('notify_only');

            // نطاق الأقسام؛ الفارغ يعني كل الأقسام.
            $table->json('section_ids')->nullable();
            // نطاق المزودين؛ الفارغ يعني كل المزودين.
            $table->json('provider_ids')->nullable();

            // آخر تقييم وآخر تفعيل مختلفان: قاعدة تُقيَّم كل ساعة وقد لا تُفعَّل شهرًا.
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamp('last_triggered_at')->nullable();
            $table->decimal('last_value', 14, 4)->nullable();
            $table->unsignedInteger('trigger_count')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'is_enabled']);
            $table->index(['project_id', 'metric']);
        });

        Schema::create('alert_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();

            // إما عضو في المشروع وإما بريد خارجي — لا يجتمعان ولا يغيبان معًا.
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('email')->nullable();

            $table->string('channel')->default('in_app');
            $table->timestamps();

            $table->unique(['alert_rule_id', 'user_id', 'email', 'channel'], 'alert_recipients_unique');
        });

        Schema::create('alert_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alert_rule_id')->constrained()->cascadeOnDelete();

            $table->string('status')->default('triggered');
            $table->string('severity');

            // اللقطة: ما كانت القاعدة عليه لحظة التفعيل. تعديل القاعدة لاحقًا
            // لا يعيد كتابة تاريخها.
            $table->string('metric');
            $table->string('comparison');
            $table->decimal('threshold', 12, 2);
            $table->decimal('observed_value', 14, 4);
            $table->decimal('peak_value', 14, 4);
            $table->unsignedInteger('window_minutes');

            // تفصيل ما لوحظ: العيّنة والنطاق.
            $table->json('context')->nullable();

            $table->timestamp('triggered_at');
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['alert_rule_id', 'triggered_at']);
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('alert_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('channel');
            $table->string('target');
            $table->string('status')->default('pending');
            $table->string('note')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('alert_events');
        Schema::dropIfExists('alert_recipients');
        Schema::dropIfExists('alert_rules');
    }
};
