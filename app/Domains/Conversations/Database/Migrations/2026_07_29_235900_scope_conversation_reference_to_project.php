<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المرجع فريدٌ **داخل مشروعه** لا في المنصّة كلها.
 *
 * المرجع يولّده المشروع الخارجي من عدّاده هو، فمشروعان يبدآن من `conv-1` أمرٌ
 * عاديّ لا نادر. وكان الفهرس عامًّا، فأول محادثةٍ يرسلها المشروع الثاني بمرجعٍ
 * استعمله الأول تسقط بـ**٥٠٠** — عيبٌ لا يظهر إلا حين يتكامل مشروعان، أي عند
 * التوسّع لا عند التجربة.
 *
 * والتفرّد لا يُلغى بل يضيق: إعادةُ الإرسال داخل المشروع الواحد يجب أن تُحدّث
 * لا أن تضاعف كل إحصاء.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique('conversations_reference_unique');
            $table->unique(['project_id', 'reference'], 'conversations_project_reference_unique');
        });
    }

    public function down(): void
    {
        // العودة تتطلب مراجعًا فريدة عالميًّا: صفوفٌ صارت متكرّرة بين مشروعين
        // تمنع إعادة الفهرس العام. تُرقَّم المكرّرات بلاحقة مشروعها بدل أن
        // تُحذف — وحذفُ محادثةٍ لإرضاء فهرس يمحو أثرًا حقيقيًّا.
        DB::statement(<<<'SQL'
            UPDATE conversations SET reference = reference || '-p' || project_id
            WHERE id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (PARTITION BY reference ORDER BY id) AS seat
                    FROM conversations
                ) ranked WHERE ranked.seat > 1
            )
        SQL);

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique('conversations_project_reference_unique');
            $table->unique('reference', 'conversations_reference_unique');
        });
    }
};
