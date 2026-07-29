<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * المشروع كيانٌ أول — ADR-0003.
 *
 * ترتيبها قبل هجرات المزودين والمحادثات (090000 < 100000) حتى تستطيع تلك
 * الجداول أن تشير إليها بمفتاح خارجي عند إنشائها في قاعدة جديدة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // عنوان REST الخاص بالمشروع — لا اتصال بقاعدة بياناته (وثيقة 02 §5).
            $table->string('api_base_url')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // الدور النافذ داخل هذا المشروع وحده — ADR-0003 §3.
            $table->string('role');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'project_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            // حامله يُعامَل كعضو بدور SystemAdmin في كل مشروع — عضوية لا استثناء.
            $table->boolean('is_platform_admin')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_platform_admin');
        });

        Schema::dropIfExists('project_user');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS projects CASCADE');

            return;
        }

        Schema::dropIfExists('projects');
    }
};
