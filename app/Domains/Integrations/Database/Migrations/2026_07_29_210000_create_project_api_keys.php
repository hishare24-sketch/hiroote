<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مفاتيح الوصول لكل مشروع — وثيقة 02 §5.
 *
 * المفتاح يُخزَّن مجزّأً (hash) لا مشفَّرًا: التشفير قابل للفكّ، ومن يقرأ نسخة
 * من قاعدة البيانات يستعيد كل مفتاح. التجزئة لا تُفكّ، فسرقة الجدول لا تمنح
 * وصولًا. وثمن ذلك أن المفتاح يُعرض مرة واحدة عند إنشائه ولا يُسترجع — وهو
 * ثمنٌ صحيح: مفتاحٌ يمكن استرجاعه من الشاشة مفتاحٌ يمكن سرقته منها.
 *
 * و`prefix` مخزَّن ظاهرًا ليعرف المشغّل أي مفتاح يُبطل حين يملك عدة مفاتيح.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_api_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('prefix', 16);
            $table->string('hash', 64)->unique();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'revoked_at']);
        });

        Schema::table('conversations', function (Blueprint $table): void {
            // الشاشة التي فُتح منها الشات. بدونه لا يمكن قياس أثر تعديل وصف
            // شاشةٍ على نسبة الحل فيها — وهو ما تنتهي إليه دورة الرصد.
            $table->string('screen_key')->nullable()->after('section');
            $table->index(['project_id', 'screen_key']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'screen_key']);
            $table->dropColumn('screen_key');
        });

        Schema::dropIfExists('project_api_keys');
    }
};
