<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Local/dev seed only. Production operators are created through the users
     * screen with an audited action, never seeded.
     */
    public function run(): void
    {
        $this->operator(Role::SystemAdmin, 'مدير النظام', 'admin@hiroote.test');

        foreach ([
            Role::AiManager,
            Role::KnowledgeManager,
            Role::CostAnalyst,
            Role::SupportAgent,
            Role::SecurityAuditor,
        ] as $role) {
            $this->operator($role, $role->label(), "{$role->value}@hiroote.test");
        }

        // المشاريع أولًا: كل ما بعدها ينتمي إلى مشروع.
        $this->call(ProjectsSeeder::class);
        $this->call(ProvidersSeeder::class);
        $this->call(SectionsSeeder::class);
        // شاشات موازين بمفاتيحها: بلا هذه لا يجد جسر الوارد ما يجيب به.
        $this->call(MawazinScreensSeeder::class);
        $this->call(DemoDataSeeder::class);
        $this->call(KnowledgeSeeder::class);
        // بعد الجميع: قواعد التنبيه تُقيَّم على البيانات المزروعة قبلها.
        $this->call(AlertsSeeder::class);
    }

    /**
     * حساب تشغيلي يُنشأ مرة واحدة.
     *
     * إعادة الزرع على قاعدة مزروعة سلفًا كانت تسقط باصطدام البريد الفريد قبل
     * أن تصل البذور التالية — فمن يهاجر ويزرع بعد سحب جديد يخسر بيانات
     * الموجة الجديدة كلها بسبب مستخدمٍ موجود. الحساب الموجود يُترك بدوره
     * وعضويته كما هما: تعديل دور مشغّلٍ قائم فعلٌ إداريّ لا وظيفةُ بذرة.
     */
    private function operator(Role $role, string $name, string $email): void
    {
        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        User::factory()->role($role)->create(['name' => $name, 'email' => $email]);
    }
}
