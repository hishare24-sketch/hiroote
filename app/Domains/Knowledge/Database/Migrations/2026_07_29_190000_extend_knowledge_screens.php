<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ما تحتاجه الشاشة لتكون سياقًا صالحًا للمساعد.
 *
 * `key` هو ما سيرسله المشروع الخارجي حين يفتح المستخدم الشات من شاشته: مفتاح
 * ثابت مثل `wallet.withdraw` لا مسارًا ولا اسمًا. المسار يتغيّر بإعادة تنظيم
 * التوجيه، والاسم يتغيّر بتحسين العرض، وكلاهما يقطع الصلة بصمت فيفقد المساعد
 * سياقه دون أن ينبّه أحد. المفتاح لا يتغيّر إلا بقرار، وإن أُرسل مفتاح لا وجود
 * له ظهر الخطأ فورًا.
 *
 * وبيانات الصورة تُحفظ معها: نوعها وحجمها. صورةٌ بلا نوع مسجَّل تُعرض بثقة ثم
 * تنكسر عند أول ملف غير متوقَّع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_screens', function (Blueprint $table): void {
            $table->string('key')->nullable()->after('section_id');
            $table->string('image_mime')->nullable()->after('image_path');
            $table->unsignedBigInteger('image_size')->nullable()->after('image_mime');
        });

        $this->backfillKeys();

        // فريد حيث وُجد فقط: الشاشة بلا مفتاح مسموحة (لم تُربط بعد)، والمكرَّر
        // ممنوع لأن مفتاحين متطابقين يجعلان السياق عشوائيًا.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX knowledge_screens_project_key_unique
                 ON knowledge_screens (project_id, key) WHERE key IS NOT NULL',
            );

            return;
        }

        Schema::table('knowledge_screens', function (Blueprint $table): void {
            $table->unique(['project_id', 'key'], 'knowledge_screens_project_key_unique');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS knowledge_screens_project_key_unique');
        }

        Schema::table('knowledge_screens', function (Blueprint $table): void {
            $table->dropColumn(['key', 'image_mime', 'image_size']);
        });
    }

    /** يشتق مفتاحًا أوليًا من المسار للشاشات القائمة، ويترك الباقي فارغًا. */
    private function backfillKeys(): void
    {
        foreach (DB::table('knowledge_screens')->whereNotNull('path')->get() as $screen) {
            $key = trim((string) $screen->path, '/');
            $key = preg_replace('/[^a-zA-Z0-9\/_-]+/', '', $key) ?? '';
            $key = str_replace('/', '.', $key);

            if ($key === '') {
                continue;
            }

            $taken = DB::table('knowledge_screens')
                ->where('project_id', $screen->project_id)
                ->where('key', $key)
                ->exists();

            if ($taken) {
                continue;
            }

            DB::table('knowledge_screens')->where('id', $screen->id)->update(['key' => $key]);
        }
    }
};
