<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سلوك المساعد وأقسام المشروع — وثيقة 06 §12 و§13 و§14.
 *
 * كل جدول هنا يحمل `project_id`: «لكل مشروع احتياجاته وطريقة تشغيله وأدواته»
 * (ADR-0003)، فأقسام مشروع ليست أقسام غيره، ووظيفةٌ مفعّلة هنا قد تكون محظورة
 * هناك. لا صفَّ مشتركًا بين مشروعين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_sections', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            // القدرات الإحدى عشرة — أعمدة المصفوفة في وثيقة 06 §14.
            $table->boolean('ai_enabled')->default(true);
            $table->boolean('knowledge')->default(true);
            $table->boolean('database_link')->default(true);
            $table->boolean('api_call')->default(true);
            $table->boolean('show_data')->default(true);
            $table->boolean('suggest_action')->default(true);
            $table->boolean('execute_action')->default(false);
            $table->boolean('read_files')->default(false);
            $table->boolean('create_ticket')->default(true);
            $table->boolean('human_handoff')->default(true);
            $table->boolean('feedback')->default(true);

            // المستوى والنموذج المخصصان للقسم؛ null = ما يرثه من إعداد المشروع.
            $table->string('level')->nullable();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'slug']);
            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('assistant_profiles', function (Blueprint $table): void {
            $table->id();
            // صفٌّ واحد لكل مشروع — إعداد تحكم المستخدم في وثيقة 06 §12.
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('default_level')->default('balanced');
            $table->boolean('allow_level_change')->default(true);

            // دائم عبر المحادثات أم يعود للافتراضي عند كل محادثة.
            $table->string('level_scope')->default('persistent');

            // من يحق له تغيير المستوى: الكل أو عضوية بعينها أو دور بعينه.
            $table->string('availability')->default('all');
            $table->string('availability_key')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('assistant_levels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key');

            $table->string('label');
            $table->text('description');

            // بطاقة المستوى — وثيقة 06 §12.
            $table->string('response_length');
            $table->unsignedInteger('token_limit');
            $table->unsignedTinyInteger('intelligence');
            $table->unsignedTinyInteger('initiative');
            $table->unsignedTinyInteger('creativity');
            $table->unsignedTinyInteger('detail');
            $table->unsignedTinyInteger('formality');

            $table->boolean('reads_attachments')->default(true);
            $table->boolean('calls_data')->default(true);
            $table->boolean('executes_actions')->default(false);

            // تحت هذه العتبة يُحوَّل بدل أن يخمّن.
            $table->unsignedTinyInteger('confidence_threshold')->default(70);

            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->decimal('expected_cost', 8, 4)->default(0);

            $table->boolean('is_available')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'key']);
            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('assistant_functions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->boolean('is_enabled');

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_functions');
        Schema::dropIfExists('assistant_levels');
        Schema::dropIfExists('assistant_profiles');
        Schema::dropIfExists('project_sections');
    }
};
