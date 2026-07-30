<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Administration\Actions\SaveUser;
use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Projects\Actions\SaveProject;
use App\Domains\Projects\Actions\SaveProjectMembership;
use App\Domains\Projects\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * أول مشروع وأول مدير على قاعدة إنتاج فارغة.
 *
 * قاعدةٌ مهاجَرة بلا مستخدم ولا مشروع تعني لوحةً لا يدخلها أحد — والبذرة
 * التطويرية (`DatabaseSeeder`) لا تصلح بديلًا: تزرع ستة حسابات بكلمة مرور
 * معروفة وبيانات تجريبية تُقرأ حركةً حقيقية في اللوحة.
 *
 * والدور وحده لا يمنح شيئًا: الصلاحية تُحلّ لكل مشروع من `project_user`
 * (ADR-0003 §3)، فمديرٌ بلا عضوية يدخل ولا يرى شيئًا. لذلك يُنشئ هذا الأمر
 * الثلاثة معًا — المشروع والحساب والعضوية — أو لا يُنشئ شيئًا.
 */
class BootstrapPlatform extends Command
{
    protected $signature = 'hiroote:bootstrap
        {--name= : اسم المدير}
        {--email= : بريده}
        {--password= : كلمة مروره (١٢ محرفًا فأكثر)}
        {--project= : اسم أول مشروع}';

    protected $description = 'ينشئ أول مشروع وأول مدير منصة على قاعدة فارغة';

    public function handle(SaveUser $users, SaveProject $projects, SaveProjectMembership $memberships): int
    {
        // لا يعمل على قاعدةٍ فيها مشغّلون: أمرٌ يُشغَّل مرتين سهوًا يجب ألّا
        // يمنح أحدًا صلاحية جديدة صامتًا.
        if (User::query()->exists()) {
            $this->components->error('القاعدة تحوي حسابات بالفعل — هذا الأمر لأول إقلاع وحده.');
            $this->line('  لإضافة مشغّل: المستخدمون والصلاحيات ← إضافة حساب (فعلٌ مسجَّل في التدقيق).');

            return self::FAILURE;
        }

        // الهجرة تُنشئ مشروعًا افتراضيًّا، فالقاعدة المهاجَرة ليست بلا مشاريع.
        // وإنشاء ثانٍ هنا يترك مشروعًا فارغًا يلتبس بالعامل في كل قائمة.
        $existing = Project::query()->orderBy('id')->get();

        $data = [
            'project' => $existing->isEmpty()
                ? ($this->option('project') ?? $this->ask('اسم أول مشروع', 'Hi-Share'))
                : $existing->first()->name,
            'name' => $this->option('name') ?? $this->ask('اسم المدير'),
            'email' => $this->option('email') ?? $this->ask('بريده'),
            'password' => $this->option('password') ?? $this->secret('كلمة المرور (١٢ محرفًا فأكثر)'),
        ];

        $validator = Validator::make($data, [
            'project' => ['required', 'string', 'min:2', 'max:80'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            // اثنا عشر لا ثمانية: هذا الحساب يفتح كل مشروع وكل مفتاح في اللوحة.
            'password' => ['required', 'string', 'min:12', 'max:200'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        // الكل في معاملة واحدة: مشروعٌ بلا مدير، أو مديرٌ بلا عضوية، حالةٌ
        // نصفُ مكتملة يصعب تمييزها عن الاكتمال عند إعادة التشغيل — والأمر
        // يرفض العمل ثانيةً لوجود حساب، فيبقى المالك خارج لوحته بلا مخرج.
        $count = DB::transaction(function () use ($data, $existing, $users, $projects, $memberships): int {
            $targets = $existing->isNotEmpty() ? $existing : collect([
                $projects->handle([
                    'name' => $data['project'],
                    'description' => 'أول مشروع في مركز التحكم.',
                    'is_active' => true,
                ]),
            ]);

            $user = $users->handle(null, [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => Role::SystemAdmin,
                'password' => $data['password'],
                'is_platform_admin' => true,
            ]);

            // «مدير منصة» عضويةٌ بدور مدير النظام في **كل** مشروع، لا استثناءٌ
            // من البوابة (CLAUDE.md رقم ٣). فمنحُها في مشروعٍ واحد يترك حاملها
            // أعمى عن البقية بينما تسمّيه الشاشة مدير منصة.
            foreach ($targets as $project) {
                $memberships->handle($project, $user, Role::SystemAdmin);
            }

            return $targets->count();
        });

        $this->newLine();
        $this->components->info("جاهز — وعضويته في {$count} مشروعًا. ادخل بالبريد وكلمة المرور اللذين أدخلتهما.");
        $this->line('  ولا يُرسَل بريد تحقق: الحساب يُسلَّم يدًا بيد، واللوحة تقولها.');

        return self::SUCCESS;
    }
}
