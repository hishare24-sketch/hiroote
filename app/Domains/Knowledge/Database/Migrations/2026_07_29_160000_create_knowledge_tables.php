<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قاعدة المعرفة — وثيقة 06 §15.
 *
 * وثيقة 02 §7 تعدّ `knowledge_sections` جدولًا مستقلًا، ولم يُنشأ: القسم كيان
 * قائم بالفعل في `project_sections` (ADR-0003)، وجدولان يصفان القسم الواحد
 * يعنيان حقيقتين تتباعدان عند أول تعديل. المعرفة تُعلَّق على القسم القائم.
 *
 * وكذلك دُمج `knowledge_files` في `knowledge_sources`: الرابط والملف والملاحظة
 * إجابات ثلاث لسؤال واحد — «من أين جاءت هذه المعرفة» — ويميّزها عمود `kind`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_items', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()
                ->constrained('project_sections')->nullOnDelete();

            $table->string('title');
            $table->string('kind')->default('article');
            $table->string('status')->default('draft');
            $table->text('summary')->nullable();
            $table->longText('body');

            // يزيد مع كل نشر؛ يقابل أحدث صفٍّ في `knowledge_versions`.
            $table->unsignedInteger('version')->default(1);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'section_id']);
            $table->index(['project_id', 'status']);
        });

        Schema::create('knowledge_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            // الوسم يخص مشروعه: «سحب» في مشروع مالي ليس «سحب» في مشروع مشاركات.
            $table->unique(['project_id', 'slug']);
        });

        Schema::create('knowledge_item_tag', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_tag_id')->constrained()->cascadeOnDelete();

            $table->unique(['knowledge_item_id', 'knowledge_tag_id']);
        });

        Schema::create('knowledge_screens', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('project_sections')->cascadeOnDelete();

            $table->string('name');
            $table->string('path')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();

            // عناصر الشاشة وإجراءاتها وحالاتها — وثيقة 06 §15.
            $table->jsonb('elements')->nullable();
            $table->jsonb('actions')->nullable();
            $table->jsonb('states')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'section_id']);
        });

        Schema::create('knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()
                ->constrained('project_sections')->nullOnDelete();
            $table->foreignId('knowledge_item_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('kind');
            $table->string('label');
            $table->string('url')->nullable();
            $table->text('note')->nullable();

            $table->string('file_path')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'section_id']);
        });

        Schema::create('knowledge_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');

            // لقطة كاملة لا فرقًا: المقارنة والرجوع يحتاجان النص كما كان، لا
            // إعادة بنائه بتطبيق فروق متتابعة قد ينكسر أحدها.
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->string('status');

            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['knowledge_item_id', 'version']);
        });

        Schema::create('knowledge_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()
                ->constrained('project_sections')->nullOnDelete();
            $table->foreignId('knowledge_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();

            // ملاحظة مستخدم، أو سؤال بلا إجابة، أو اقتراح من المساعد نفسه.
            $table->string('kind');
            $table->text('body');
            $table->unsignedTinyInteger('occurrences')->default(1);

            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['project_id', 'kind']);
            $table->index(['section_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_feedback');
        Schema::dropIfExists('knowledge_versions');
        Schema::dropIfExists('knowledge_sources');
        Schema::dropIfExists('knowledge_screens');
        Schema::dropIfExists('knowledge_item_tag');
        Schema::dropIfExists('knowledge_tags');
        Schema::dropIfExists('knowledge_items');
    }
};
