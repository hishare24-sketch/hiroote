<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * النبض اليومي للمشروع — دفعةٌ مجمَّعة يرسلها المشروع مرة كل يوم.
 *
 * **كل مقياس `nullable` عمدًا، ولا افتراضي له.** قاعدة هذه اللوحة أن الرقم
 * الذي لا يُقاس ليس صفرًا، و`default(0)` هنا كان سيمحو الفرق إلى الأبد: يومٌ
 * لم يُقس فيه التخزين يظهر «صفر ميغابايت» ويدخل المتوسط ويهبط به، ولا شيء
 * يقول إنه لم يُقس.
 *
 * ولا هويّات: مجاميعُ لا صفوفٌ لفرد (قرار المالك ٢٠٢٦-٠٧-٣٠). المقاييس أعدادٌ
 * ومعدّلات، ومن هم النشطون لا يخصّ هذه اللوحة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_pulses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();

            $table->date('pulse_date');

            // المنطقة الزمنية تُحفظ مع اليوم لا تُفترض: «يوم» في الرياض غير
            // «يوم» في UTC بثلاث ساعات، وذروة ساعةٍ تنتقل بينهما. ومقارنةُ
            // يومين حُسبا بحدّين مختلفين تُنتج ميلًا خفيفًا في كل منحنى لا
            // يظهر في أي رقم مفرد.
            $table->string('timezone', 64);

            // يومٌ لم ينتهِ يُعلن ناقصًا: دفعةٌ جزئية بلا هذه الراية تُقرأ
            // هبوطًا — وهو أسوأ من غياب اليوم، لأن الغياب يُسأل عنه والهبوط
            // يُفسَّر.
            $table->boolean('is_final')->default(true);

            // الاستبدال يترك أثرًا: رسمٌ لشهرٍ مضى قد يتغيّر رجعيًّا، ومن حقّ
            // من يقرأه أن يعرف أنه تغيّر.
            $table->unsignedInteger('revision')->default(1);

            $table->unsignedInteger('active_users')->nullable();
            $table->unsignedInteger('logins')->nullable();
            $table->unsignedInteger('sessions')->nullable();
            $table->unsignedInteger('peak_concurrent')->nullable();
            $table->unsignedBigInteger('presence_minutes')->nullable();

            // 0..23 في المنطقة الزمنية أعلاه.
            $table->unsignedTinyInteger('peak_hour')->nullable();
            $table->unsignedInteger('peak_hour_actions')->nullable();

            $table->unsignedBigInteger('storage_megabytes')->nullable();

            // إجراءات كل قسم: {اسم القسم: عدد}
            $table->json('section_actions')->nullable();

            // [{name, subscribers}] — عددٌ في كل باقة لا مَن فيها.
            $table->json('packages')->nullable();

            // مؤشرات صحّة يسمّيها المُرسِل: لا نخترع لها معنى ولا نحسبها.
            // {اسم المؤشّر: قيمة} — بأسماء المُرسِل.
            $table->json('health_indicators')->nullable();

            $table->timestamps();

            $table->unique(['project_id', 'pulse_date']);
            $table->index(['project_id', 'pulse_date', 'is_final']);
        });

        // أثر الاستبدال: القيمة المُزاحة تُحفظ كما كانت.
        //
        // بلا هذا الجدول يتغيّر رسمٌ بيانيّ لشهرٍ مضى ولا يعرف أحدٌ لماذا، ولا
        // سبيل للإجابة عن «هل كان الرقم هكذا حين اتُّخذ القرار؟». والعدّاد
        // وحده يقول إنّ تغييرًا وقع ولا يقول ماذا كان.
        Schema::create('project_pulse_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_pulse_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');

            // الحمولة كما كانت قبل الاستبدال.
            $table->json('superseded_values');

            $table->timestamp('replaced_at')->useCurrent();

            $table->index(['project_pulse_id', 'revision']);
        });

        Schema::create('project_screen_pulses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_pulse_id')->constrained()->cascadeOnDelete();
            $table->string('screen_key', 120);

            $table->unsignedInteger('views')->nullable();
            $table->unsignedInteger('clicks')->nullable();

            $table->unique(['project_pulse_id', 'screen_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_screen_pulses');
        Schema::dropIfExists('project_pulse_revisions');
        Schema::dropIfExists('project_pulses');
    }
};
