<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * جداول الاستهلاك والتكلفة — وثيقة 02 §7.
 *
 * سجلات التكلفة immutable (وثيقة 02 §8) — تُحمى بنفس أسلوب `audit_logs`:
 * trigger في PostgreSQL لا يعتمد على انضباط الكود وحده.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->foreignId('model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->string('section')->nullable()->index();

            // تفصيل التوكن حسب الاستخدام — وثيقة التصميم §7.
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('knowledge_tokens')->default(0);
            $table->unsignedBigInteger('attachment_tokens')->default(0);
            $table->unsignedBigInteger('tool_tokens')->default(0);

            $table->date('recorded_on')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['recorded_on', 'provider_id']);
        });

        Schema::create('cost_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('ai_providers')->nullOnDelete();
            $table->string('section')->nullable()->index();
            $table->string('operation')->nullable();

            $table->decimal('amount', 12, 4);
            $table->string('currency', 3)->default('SAR');

            $table->date('recorded_on')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['recorded_on', 'provider_id']);
        });

        Schema::create('usage_budgets', function (Blueprint $table): void {
            $table->id();
            // null = الميزانية العامة؛ غير ذلك ميزانية قسم أو مزود.
            $table->string('scope')->default('platform');
            $table->string('scope_key')->nullable();
            $table->decimal('monthly_limit', 12, 2);
            $table->string('currency', 3)->default('SAR');
            $table->unsignedTinyInteger('warn_at_percent')->default(70);
            $table->unsignedTinyInteger('critical_at_percent')->default(85);
            $table->boolean('hard_stop')->default(false);
            $table->timestamps();

            $table->unique(['scope', 'scope_key']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION hiroote_usage_append_only()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION '% is append-only: % is not permitted', TG_TABLE_NAME, TG_OP;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER token_usage_no_update BEFORE UPDATE ON token_usage_records
                    FOR EACH ROW EXECUTE FUNCTION hiroote_usage_append_only();
                CREATE TRIGGER token_usage_no_delete BEFORE DELETE ON token_usage_records
                    FOR EACH ROW EXECUTE FUNCTION hiroote_usage_append_only();
                CREATE TRIGGER cost_usage_no_update BEFORE UPDATE ON cost_usage_records
                    FOR EACH ROW EXECUTE FUNCTION hiroote_usage_append_only();
                CREATE TRIGGER cost_usage_no_delete BEFORE DELETE ON cost_usage_records
                    FOR EACH ROW EXECUTE FUNCTION hiroote_usage_append_only();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS token_usage_no_update ON token_usage_records;
                DROP TRIGGER IF EXISTS token_usage_no_delete ON token_usage_records;
                DROP TRIGGER IF EXISTS cost_usage_no_update ON cost_usage_records;
                DROP TRIGGER IF EXISTS cost_usage_no_delete ON cost_usage_records;
                DROP FUNCTION IF EXISTS hiroote_usage_append_only();
            SQL);
        }

        Schema::dropIfExists('usage_budgets');
        Schema::dropIfExists('cost_usage_records');
        Schema::dropIfExists('token_usage_records');
    }
};
