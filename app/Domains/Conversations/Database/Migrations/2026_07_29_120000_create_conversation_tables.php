<?php

declare(strict_types=1);

use App\Domains\Conversations\Enums\ConversationOutcome;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول Domain المحادثات — وثيقة 02 §7.
 *
 * هوية مستخدم Hi-Share تبقى في Hi-Share (وثيقة 01 §6): نحفظ معرّفه الخارجي
 * واسم العرض وقت المحادثة فقط، ولا ننشئ له سجل مستخدم عندنا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            // المرجع الظاهر للمشغّل (#HS-58421) — ثابت وقابل للمشاركة.
            $table->string('reference')->unique();
            $table->ulid('ulid')->unique();

            $table->string('external_user_id')->nullable()->index();
            $table->string('user_label')->nullable();

            $table->string('section')->index();
            $table->string('assistant')->nullable();
            $table->string('level');

            $table->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();

            $table->string('detected_intent')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();

            $table->string('outcome')->default(ConversationOutcome::Open->value)->index();
            $table->boolean('resolved_first_answer')->default(false);
            $table->boolean('understood_intent')->default(true);
            $table->boolean('rephrased')->default(false);

            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->decimal('cost', 10, 4)->default(0);
            $table->unsignedInteger('first_response_ms')->nullable();
            $table->unsignedInteger('avg_response_ms')->nullable();

            // 1.0–5.0 — يبقى null حين لا يقيّم المستخدم.
            $table->decimal('rating', 2, 1)->nullable();

            $table->timestamp('started_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['section', 'started_at']);
            $table->index(['outcome', 'started_at']);
        });

        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->unsignedInteger('tokens')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('conversation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // خط زمني موحّد: استدعاء أداة، فتح شاشة، نقرة، تصعيد…
            $table->string('type')->index();
            $table->string('label');
            $table->text('detail')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('conversation_tools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('outcome');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('conversation_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('screen');
            $table->string('path')->nullable();
            $table->boolean('led_to_resolution')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('conversation_escalations', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('reference')->unique();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();

            $table->string('target')->index();
            $table->string('severity')->index();
            $table->string('reason');
            $table->string('section')->index();
            $table->string('subject');

            $table->unsignedInteger('wait_seconds')->nullable();
            $table->unsignedInteger('handling_seconds')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['target', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_escalations');
        Schema::dropIfExists('conversation_clicks');
        Schema::dropIfExists('conversation_tools');
        Schema::dropIfExists('conversation_events');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
