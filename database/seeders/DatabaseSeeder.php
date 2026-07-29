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
        User::factory()->role(Role::SystemAdmin)->create([
            'name' => 'مدير النظام',
            'email' => 'admin@hiroote.test',
        ]);

        foreach ([
            Role::AiManager,
            Role::KnowledgeManager,
            Role::CostAnalyst,
            Role::SupportAgent,
            Role::SecurityAuditor,
        ] as $role) {
            User::factory()->role($role)->create([
                'name' => $role->label(),
                'email' => "{$role->value}@hiroote.test",
            ]);
        }

        // المشاريع أولًا: كل ما بعدها ينتمي إلى مشروع.
        $this->call(ProjectsSeeder::class);
        $this->call(ProvidersSeeder::class);
        $this->call(DemoDataSeeder::class);
    }
}
