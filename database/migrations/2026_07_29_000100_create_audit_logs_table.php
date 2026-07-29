<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * سجل التدقيق — وثيقة 01 §5-ط، وثيقة 02 §8، وثيقة التصميم §17.
 *
 * الجدول append-only على مستوى قاعدة البيانات وليس على مستوى الكود وحده:
 * تعديل الكود أو الوصول المباشر بـ psql لا يستطيع تجاوز الـ trigger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();

            // نسخة ثابتة من هوية الفاعل: حذف المستخدم لاحقًا لا يمحو أثره.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label')->nullable();
            $table->string('actor_role')->nullable();

            $table->string('action')->index();
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id')->nullable();
            $table->string('section')->nullable()->index();

            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->text('reason')->nullable();

            $table->string('request_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_id', 'created_at']);
            $table->index('created_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION hiroote_audit_logs_append_only()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_logs is append-only: % is not permitted', TG_OP;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER audit_logs_no_update
                    BEFORE UPDATE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION hiroote_audit_logs_append_only();

                CREATE TRIGGER audit_logs_no_delete
                    BEFORE DELETE ON audit_logs
                    FOR EACH ROW EXECUTE FUNCTION hiroote_audit_logs_append_only();

                CREATE TRIGGER audit_logs_no_truncate
                    BEFORE TRUNCATE ON audit_logs
                    FOR EACH STATEMENT EXECUTE FUNCTION hiroote_audit_logs_append_only();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS audit_logs_no_update ON audit_logs;
                DROP TRIGGER IF EXISTS audit_logs_no_delete ON audit_logs;
                DROP TRIGGER IF EXISTS audit_logs_no_truncate ON audit_logs;
                DROP FUNCTION IF EXISTS hiroote_audit_logs_append_only();
            SQL);
        }

        Schema::dropIfExists('audit_logs');
    }
};
