<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أثر الرصد والتحقق — الحلقة التي تسبق أي تعديل على المحتوى التعريفي.
 *
 * المساعد يرصد ولا يكتب: يقول «هنا تعثّر العميل وهذا ما سأل عنه» ثم يقف. الدعم
 * البشري يقرأ الرصد، ويتحقق منه **بتمثيل دور مستخدم على الشاشة نفسها**، ويسجّل
 * ما فعله وما وجده. عندها فقط يُعدَّل الوصف.
 *
 * ولذلك جدول التحقق مستقل عن الملاحظة: الملاحظة رصدٌ قد يصحّ وقد لا يصحّ،
 * والتحقق إثباتٌ أو نفيٌ له. دمجهما في صف واحد يجعل الرصد يبدو مثبتًا لمجرد
 * أنه سُجِّل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_feedback', function (Blueprint $table): void {
            $table->foreignId('screen_id')->nullable()->after('section_id')
                ->constrained('knowledge_screens')->nullOnDelete();

            // من رفع الملاحظة: مساعد أم مستخدم أم موظف دعم.
            $table->string('source')->default('user')->after('kind');

            $table->foreignId('assigned_to')->nullable()->after('occurrences')
                ->constrained('users')->nullOnDelete();

            // كيف أُغلقت: عولجت بتعديل، أو استُبعدت بعد تحقق نفاها.
            $table->string('resolution')->nullable()->after('resolved_at');

            $table->index(['project_id', 'screen_id']);
        });

        Schema::create('feedback_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // الجدول مفرد (`knowledge_feedback`) فيلزم تسميته صراحةً.
            $table->foreignId('knowledge_feedback_id')
                ->constrained('knowledge_feedback')->cascadeOnDelete();

            // الشاشة التي جُرِّبت فعلًا — قد تخالف المرصودة، وهذا بذاته نتيجة.
            $table->foreignId('screen_id')->nullable()
                ->constrained('knowledge_screens')->nullOnDelete();

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('outcome');
            $table->text('steps');
            $table->text('finding')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['knowledge_feedback_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_verifications');

        Schema::table('knowledge_feedback', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('screen_id');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn(['source', 'resolution']);
        });
    }
};
