<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Seeder;

/**
 * مشاريع التطوير — ADR-0003.
 *
 * مشروعان بحجمين مختلفين لا واحد: مبدّل بخيار وحيد لا يثبت أن التقييد يعمل،
 * وشاشةٌ تُختبر على مشروع واحد قد تكون تسرّب بيانات غيره ولا يظهر.
 */
class ProjectsSeeder extends Seeder
{
    public function run(): void
    {
        $hiShare = Project::query()->updateOrCreate(
            ['slug' => 'hi-share'],
            [
                'name' => 'Hi-Share',
                'description' => 'منصة المشاركات والحملات والمساحات الإعلانية.',
                'api_base_url' => config('hiroote.hishare.base_url'),
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $mawazin = Project::query()->updateOrCreate(
            ['slug' => 'mawazin'],
            [
                'name' => 'موازين',
                'description' => 'منصة المحاسبة والمحافظ والاشتراكات.',
                'api_base_url' => 'https://api.mawazin.test/v1',
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        $admin = User::query()->where('email', 'admin@hiroote.test')->first();

        if ($admin !== null) {
            // مدير المنصة يرى كل المشاريع بحكم عضويته الضمنية، ولا يحتاج صفًّا.
            $admin->forceFill(['is_platform_admin' => true])->save();
        }

        // بقية الأدوار أعضاء في Hi-Share وحده، فيظهر أثر تقييد العضوية فعليًا:
        // من ليس عضوًا في «موازين» لا يراه في المبدّل أصلًا.
        foreach (Role::cases() as $role) {
            $user = User::query()->where('email', "{$role->value}@hiroote.test")->first();

            if ($user === null) {
                continue;
            }

            $hiShare->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
        }

        // محلل التكلفة وحده يمتد إلى موازين — بدور مختلف عن دوره في Hi-Share،
        // فيثبت أن الدور يُحلّ لكل مشروع لا مرة واحدة للشخص.
        $analyst = User::query()->where('email', 'cost_analyst@hiroote.test')->first();

        if ($analyst !== null) {
            $mawazin->members()->syncWithoutDetaching([
                $analyst->id => ['role' => Role::SupportAgent->value],
            ]);
        }
    }
}
