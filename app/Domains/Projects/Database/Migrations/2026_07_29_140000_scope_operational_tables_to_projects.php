<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ربط البيانات القائمة بمشروعها — ADR-0003.
 *
 * البيانات المبنية قبل هذا القرار كلها تخص Hi-Share، فتُنسب إليه بدل أن تبقى
 * بلا انتماء: صفٌّ بلا مشروع في مركز متعدد المشاريع صفٌّ لا يُعرف من يملكه.
 */
return new class extends Migration
{
    /** الجداول المحمية بـ trigger append-only — تحتاج تعطيلًا مؤقتًا للترحيل. */
    private const APPEND_ONLY = ['audit_logs', 'token_usage_records', 'cost_usage_records'];

    /** يُرحَّل مباشرةً — لا trigger يحرسه. */
    private const DIRECT = ['conversations', 'conversation_escalations'];

    public function up(): void
    {
        $projectId = $this->ensureDefaultProject();

        // ملكية مباشرة: كل صفٍّ يخص مشروعًا واحدًا.
        // التصعيد يحمل مشروعه مباشرةً لا عبر محادثته: قائمة الحالات المفتوحة
        // تُقرأ من جدولها وحده، والمرور بـ join لتقييدها يجعل أهمّ استعلام أبطأ.
        foreach ([
            'conversations',
            'conversation_escalations',
            'token_usage_records',
            'cost_usage_records',
        ] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('project_id')->nullable()->after('id')
                    ->constrained()->cascadeOnDelete();
            });
        }

        // ملكية اختيارية: null = مشترك على مستوى الشركة (ADR-0003 §2).
        foreach (['usage_budgets', 'audit_logs', 'ai_provider_credentials', 'provider_settings'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('project_id')->nullable()->after('id')
                    ->constrained()->nullOnDelete();
            });
        }

        $this->backfill($projectId);

        // المحادثة والتصعيد بلا مشروع لا معنى لهما — يُشدَّدان بعد الترحيل لا قبله.
        DB::statement('ALTER TABLE conversations ALTER COLUMN project_id SET NOT NULL');
        DB::statement('ALTER TABLE conversation_escalations ALTER COLUMN project_id SET NOT NULL');

        Schema::table('conversations', function (Blueprint $table): void {
            $table->index(['project_id', 'started_at']);
        });

        Schema::table('conversation_escalations', function (Blueprint $table): void {
            $table->index(['project_id', 'created_at']);
        });

        foreach (['token_usage_records', 'cost_usage_records'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->index(['project_id', 'recorded_on']);
            });
        }

        // المفتاح وحده لم يعد فريدًا: نفس السويتش قد يُخصَّص لكل مشروع.
        Schema::table('provider_settings', function (Blueprint $table): void {
            $table->dropUnique('provider_settings_key_unique');
            $table->unique(['key', 'project_id']);
        });

        Schema::table('usage_budgets', function (Blueprint $table): void {
            $table->dropUnique('usage_budgets_scope_scope_key_unique');
            $table->unique(['scope', 'scope_key', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::table('usage_budgets', function (Blueprint $table): void {
            $table->dropUnique(['scope', 'scope_key', 'project_id']);
            $table->unique(['scope', 'scope_key']);
        });

        Schema::table('provider_settings', function (Blueprint $table): void {
            $table->dropUnique(['key', 'project_id']);
            $table->unique('key');
        });

        foreach ([
            'conversations',
            'conversation_escalations',
            'token_usage_records',
            'cost_usage_records',
            'usage_budgets',
            'audit_logs',
            'ai_provider_credentials',
            'provider_settings',
        ] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('project_id');
            });
        }
    }

    /**
     * ينشئ مشروع Hi-Share إن لم يوجد ويعيد معرّفه.
     */
    private function ensureDefaultProject(): int
    {
        $existing = DB::table('projects')->orderBy('id')->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('projects')->insertGetId([
            'ulid' => (string) Str::ulid(),
            'name' => 'Hi-Share',
            'slug' => 'hi-share',
            'description' => 'منصة المشاركات والحملات — المشروع الأول في مركز التحكم.',
            'api_base_url' => config('hiroote.hishare.base_url'),
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * ينسب الصفوف القائمة إلى المشروع الافتراضي.
     *
     * جدولا الاستهلاك يرفضان أي UPDATE بحكم الـ trigger، فيُعطَّل الـ trigger
     * لهذه العبارة وحدها ثم يُعاد فورًا — الحماية قائمة قبل الهجرة وبعدها،
     * والنافذة داخل معاملة الهجرة نفسها.
     */
    private function backfill(int $projectId): void
    {
        foreach (self::DIRECT as $table) {
            DB::table($table)->whereNull('project_id')->update(['project_id' => $projectId]);
        }

        foreach (self::APPEND_ONLY as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE {$table} DISABLE TRIGGER USER");
            }

            DB::table($table)->whereNull('project_id')->update(['project_id' => $projectId]);

            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE {$table} ENABLE TRIGGER USER");
            }
        }
    }
};
