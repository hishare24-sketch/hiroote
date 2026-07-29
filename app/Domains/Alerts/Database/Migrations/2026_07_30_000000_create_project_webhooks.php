<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عنوان استقبال التنبيهات في المشروع، وسرُّ توقيعها.
 *
 * منفصل عن `project_bridges` عمدًا: الجسر **يقرأ** من المشروع، وهذا **يدفع**
 * إليه. خلطهما يجعل إيقاف القراءة يوقف الإنذار، وهما قراران مستقلان.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('url', 300);
            // مشفَّر لا مجزّأ: يُوقَّع به كل دفعة، فلا بدّ من استرجاعه.
            $table->text('secret');
            $table->boolean('is_enabled')->default(true);

            $table->timestamp('last_delivered_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('last_error_at')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_webhooks');
    }
};
