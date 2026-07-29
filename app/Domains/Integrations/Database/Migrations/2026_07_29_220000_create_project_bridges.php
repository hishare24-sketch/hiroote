<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جسر صادر إلى مشروع خارجي — وثيقة 02 §5.
 *
 * عكس `project_api_keys`: ذاك يستقبل، وهذا **يخرج** — هاي روت يسأل المشروع عن
 * إعداداته وإحصاءاته ويعرضها.
 *
 * البيانات مشفَّرة لا مجزّأة هنا، بخلاف مفاتيح الاستقبال: هناك نتحقق من مفتاحٍ
 * يقدّمه غيرنا فتكفي مقارنة التجزئة، وهنا نحتاج السرّ نفسه لنقدّمه إلى موازين،
 * فلا بدّ من قابلية الفكّ. ولذلك يُحفظ ببصمة مفتاح التطبيق وحده.
 *
 * `driver` يسمّي المشروع الخارجي: لكل مشروع واجهته وعقده، ومهايئٌ واحد يفترض
 * أن كل المشاريع تتكلم لغة موازين يكسر عند أول مشروع لا يفعل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_bridges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->string('driver')->default('mawazin');
            $table->string('base_url');

            // service_account: بريد وكلمة مرور يُسجَّل بهما الدخول ويُجدَّد الرمز.
            // bearer: رمز جاهز يُلصق كما هو (أسرع، وينتهي فجأة).
            $table->string('auth_mode')->default('service_account');
            $table->text('credentials')->nullable();

            $table->boolean('is_enabled')->default(true);

            // آخر جلب ناجح وآخر إخفاق — يُعرضان معًا: جسرٌ يقول «متصل» وآخر
            // نجاحه أمس ليس متصلًا.
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_bridges');
    }
};
