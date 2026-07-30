<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Administration\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * إعادة ضبط كلمة مرور مشغّل من الخادم — المخرج الوحيد من الإغلاق.
 *
 * شاشة المستخدمين تحتاج دخولًا، فمالكٌ نسي كلمة مروره لا يملك سبيلًا إليها:
 * ولا بريدَ استعادة (`MAIL_MAILER=log` حتى يُضبط مُرسِل)، ولا حسابَ ثانٍ في أول
 * إقلاع. فيبقى صاحب اللوحة خارجها ولا شيء يفتحها له.
 *
 * وكلمة المرور تُطبع **وحدها** على المخرج القياسي، فيُمكن تحويلها إلى ملف بلا
 * أن تمرّ بالشاشة: من ينسخ سرًّا من شاشة يتركه في سجلّ الطرفية وفي كل صورة
 * تُؤخذ بعده.
 */
class ResetOperatorPassword extends Command
{
    protected $signature = 'hiroote:reset-password
        {email : بريد المشغّل}
        {--password= : كلمة مرور بعينها (وإلا وُلّدت)}
        {--activate : يُفعّل الحساب إن كان معطّلًا}';

    protected $description = 'يعيد ضبط كلمة مرور مشغّل ويطبعها — للاستعادة من الخادم';

    public function handle(RecordAuditEntry $audit): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User) {
            $this->components->error("لا حساب بالبريد {$email}.");

            $known = User::query()->orderBy('id')->pluck('email');

            if ($known->isNotEmpty()) {
                $this->line('  الحسابات الموجودة: '.$known->implode(' · '));
            }

            return self::FAILURE;
        }

        $given = $this->option('password');
        $password = is_string($given) && $given !== '' ? $given : Str::password(20, symbols: false);

        if (mb_strlen($password) < 12) {
            $this->components->error('كلمة المرور أقصر من اثني عشر محرفًا.');

            return self::FAILURE;
        }

        $wasInactive = ! $user->is_active;

        $user->forceFill(['password' => Hash::make($password)]);

        // حسابٌ معطّل يقبل كلمة المرور ثم يُرفض برسالةٍ أخرى: إعادةُ الضبط بلا
        // تفعيلٍ تترك المشغّل يجرّب سرًّا صحيحًا ويُمنع، فيظنّ الضبط أخفق.
        if ($wasInactive && $this->option('activate') === true) {
            $user->forceFill(['is_active' => true]);
        }

        $user->save();

        $audit->handle(new AuditEntry(
            action: 'users.password_reset',
            auditable: $user,
            section: 'users',
            // لا كلمة المرور ولا جزءٌ منها — الحدثُ يُسجَّل لا قيمتُه.
            newValues: ['البريد' => $user->email, 'من' => 'الطرفية على الخادم'],
        ));

        // على المخرج القياسي وحدها، بلا زخرفة: `… > ملف` يعطي السرّ نظيفًا.
        $this->output->writeln($password);

        $notes = [];

        if ($wasInactive) {
            $notes[] = $this->option('activate') === true
                ? 'الحساب كان معطّلًا وفُعّل.'
                : 'الحساب **معطّل** — أعد الأمر بـ--activate وإلا رُفض الدخول بكلمة مرور صحيحة.';
        }

        $notes[] = 'وإن ظهر «محاولات كثيرة» فذلك قفل الدقيقة الواحدة — انتظر ستين ثانية.';

        foreach ($notes as $note) {
            $this->components->warn($note);
        }

        return self::SUCCESS;
    }
}
