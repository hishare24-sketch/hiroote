<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Analytics\Models\ProjectPulse;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * حذف نبضٍ تجاوز مدّة الحفظ.
 *
 * **مدّةُ حفظٍ بلا كانسٍ وعدٌ لا سياسة.** يُكتب «٢٤ شهرًا» في العقد، وتبقى
 * الصفوف إلى الأبد، ويطمئنّ الجميع إلى حذفٍ لا يقع. والوعد المكتوب أسوأ من
 * غيابه، لأنه يُغني عن السؤال.
 *
 * ويُقال هنا وفي الوثيقة ما لا يفعله: **النسخ الاحتياطية تمدّ الحفظ الفعليّ
 * إلى ما بعد المدّة** — نسخةٌ عمرها شهر تحمل صفوفًا حُذفت اليوم.
 */
class PrunePulses extends Command
{
    protected $signature = 'hiroote:prune-pulses {--months= : كم شهرًا يُحفظ}';

    protected $description = 'يحذف دفعات النبض الأقدم من مدّة الحفظ';

    public function handle(): int
    {
        $months = $this->option('months') === null
            ? config()->integer('hiroote.pulse.retention_months', 24)
            : (int) $this->option('months');

        if ($months < 1) {
            $this->components->error('مدّة الحفظ شهرٌ فأكثر — صفرٌ يمحو كل شيء.');

            return self::FAILURE;
        }

        $cutoff = Carbon::now()->subMonths($months)->startOfDay();

        // `delete()` على الاستعلام لا حلقةٌ على النماذج: المفاتيح الأجنبية
        // بـ`cascadeOnDelete` تتكفّل بالشاشات والمراجعات.
        $deleted = ProjectPulse::query()
            ->where('pulse_date', '<', $cutoff->toDateString())
            ->delete();

        $this->components->info(sprintf(
            'حُذف %d يومًا أقدم من %s (مدّة الحفظ %d شهرًا).',
            $deleted,
            $cutoff->toDateString(),
            $months,
        ));

        return self::SUCCESS;
    }
}
