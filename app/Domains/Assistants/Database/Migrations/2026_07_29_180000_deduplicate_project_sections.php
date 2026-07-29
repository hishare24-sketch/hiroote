<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * دمج الأقسام المكرَّرة وإغلاق باب تكرارها.
 *
 * السبب: `SectionsSeeder` كان يفتح كل قسم بمفتاح `Str::slug($name)`، وهي دالة
 * تُسقط الحروف العربية فتعيد سلسلة فارغة. حين استُبدلت بـ `Slug::make` التي
 * تحفظ العربية، لم يعد المزارع يجد صفوفه القديمة فأنشأ مجموعة ثانية كاملة —
 * ٣٢ قسمًا مكان ١٦: نسخة قديمة بلا وصف ونسخة جديدة بوصف.
 *
 * الدمج يُبقي **الأقدم** لا الأحدث: هو الصف الذي تشير إليه المعرفة والشاشات
 * والمصادر المزروعة قبله. حذفه لإبقاء الأجمل يعني فقدان ما عُلِّق عليه.
 *
 * والمفتاح صار الاسم لا الرابط: الاسم ما يراه المشغّل وما يعرّف القسم فعلًا،
 * والرابط اشتقاقٌ منه يتغيّر بتغيّر دالة الاشتقاق.
 */
return new class extends Migration
{
    /** الجداول التي تعلّق سجلّها على قسم. */
    private const CHILDREN = [
        'knowledge_items',
        'knowledge_screens',
        'knowledge_sources',
        'knowledge_feedback',
    ];

    public function up(): void
    {
        $this->mergeDuplicates();

        Schema::table('project_sections', function (Blueprint $table): void {
            $table->unique(['project_id', 'name'], 'project_sections_project_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('project_sections', function (Blueprint $table): void {
            $table->dropUnique('project_sections_project_name_unique');
        });
    }

    private function mergeDuplicates(): void
    {
        $groups = DB::table('project_sections')
            ->select('project_id', 'name')
            ->groupBy('project_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('project_sections')
                ->where('project_id', $group->project_id)
                ->where('name', $group->name)
                ->orderBy('id')
                ->get();

            $keeper = $rows->first();

            if ($keeper === null) {
                continue;
            }

            $duplicates = [];

            foreach ($rows->skip(1) as $row) {
                $duplicates[] = (int) $row->id;
            }

            if ($duplicates === []) {
                continue;
            }

            // الوصف والقدرات من الأحدث: هو ما زرعته النسخة الجديدة من المزارع.
            $latest = $rows->last();

            if ($latest !== null && $latest->id !== $keeper->id) {
                DB::table('project_sections')
                    ->where('id', $keeper->id)
                    ->update(['description' => $latest->description]);
            }

            foreach (self::CHILDREN as $table) {
                DB::table($table)
                    ->whereIn('section_id', $duplicates)
                    ->update(['section_id' => $keeper->id]);
            }

            $this->repointAlertScopes($duplicates, (int) $keeper->id);

            DB::table('project_sections')->whereIn('id', $duplicates)->delete();
        }
    }

    /**
     * نطاق الأقسام في قواعد التنبيه مصفوفةُ معرّفات في JSON لا مفتاحًا أجنبيًا،
     * فلا تتبعه قاعدة البيانات عند الحذف.
     *
     * @param  list<int>  $duplicates
     */
    private function repointAlertScopes(array $duplicates, int $keeper): void
    {
        if (! Schema::hasTable('alert_rules')) {
            return;
        }

        foreach (DB::table('alert_rules')->whereNotNull('section_ids')->get() as $rule) {
            $ids = json_decode((string) $rule->section_ids, true);

            if (! is_array($ids)) {
                continue;
            }

            $mapped = [];

            foreach ($ids as $id) {
                $mapped[] = in_array((int) $id, $duplicates, true) ? $keeper : (int) $id;
            }

            $mapped = array_values(array_unique($mapped));

            if ($mapped === $ids) {
                continue;
            }

            DB::table('alert_rules')
                ->where('id', $rule->id)
                ->update(['section_ids' => json_encode($mapped)]);
        }
    }
};
