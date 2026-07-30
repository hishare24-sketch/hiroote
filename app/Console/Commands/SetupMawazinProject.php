<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Actions\SaveProject;
use App\Domains\Projects\Actions\SaveProjectMembership;
use App\Domains\Projects\Models\Project;
use Database\Seeders\MawazinScreensSeeder;
use Database\Seeders\SectionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * تهيئة مشروع موازين في الإنتاج — بمعرّفه وأقسامه وشاشاته.
 *
 * إنشاؤه من الشاشة **لا يكفي**: المعرّف يُشتقّ من الاسم فيصير «موازين»
 * بالعربية، وبذور الأقسام والشاشات تبحث عن `mawazin` — فيُنشأ المشروع فارغًا
 * ويردّ جسر الوارد `404 screen_not_found` على كل مفتاح شاشة، بلا أن يقول أحدٌ
 * إن السبب معرّفٌ لا يطابق.
 *
 * وأعضاء المنصّة يُلحقون به: «مدير منصة» عضويةٌ بدور مدير النظام في **كل**
 * مشروع لا استثناءٌ من البوابة (CLAUDE.md رقم ٣) — ومشروعٌ جديد بلا إلحاقهم
 * يجعل مديرَ المنصّة يراه في القائمة ولا يرى شيئًا داخله.
 */
class SetupMawazinProject extends Command
{
    private const SLUG = 'mawazin';

    protected $signature = 'hiroote:setup-mawazin {--name=موازين : اسم المشروع كما يُعرض}';

    protected $description = 'ينشئ مشروع موازين بأقسامه وشاشاته الجاهزة';

    public function handle(SaveProject $projects, SaveProjectMembership $memberships): int
    {
        $project = Project::query()->where('slug', self::SLUG)->first();

        if ($project instanceof Project) {
            $this->components->info("المشروع «{$project->name}» موجود — تُحدَّث أقسامه وشاشاته فقط.");
        } else {
            $project = $projects->handle([
                'name' => (string) $this->option('name'),
                // المعرّف صريحٌ لا مشتقّ: الاشتقاق من اسمٍ عربي يعطي «موازين»،
                // والبذور تبحث عن `mawazin`.
                'slug' => self::SLUG,
                'description' => 'منصة موازين — أول مشروع موصول بجسر الوارد.',
                'is_active' => true,
            ]);

            $this->components->info("أُنشئ المشروع «{$project->name}» بالمعرّف «".self::SLUG.'».');
        }

        // `--force` لازمة في الإنتاج: البذرة تتوقّف للتأكيد وإلا، فتُقرأ
        // «مُهيَّأ» وهي لم تُنفَّذ. والبذرتان تُحدِّثان ولا تُضاعفان.
        foreach ([SectionsSeeder::class, MawazinScreensSeeder::class] as $seeder) {
            Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        foreach (User::query()->where('is_platform_admin', true)->get() as $admin) {
            $memberships->handle($project, $admin, Role::SystemAdmin);
        }

        $sections = ProjectSection::query()->where('project_id', $project->id)->count();
        $screens = KnowledgeScreen::query()->where('project_id', $project->id)->count();

        $this->newLine();
        $this->components->twoColumnDetail('الأقسام', (string) $sections);
        $this->components->twoColumnDetail('الشاشات', (string) $screens);

        if ($screens === 0) {
            $this->components->error('لا شاشة — جسر الوارد سيردّ ٤٠٤ على كل مفتاح.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('  بقي: المشاريع ← موازين ← المفاتيح ← إصدار مفتاح إنتاج، ثم ضبطه في موازين.');

        return self::SUCCESS;
    }
}
