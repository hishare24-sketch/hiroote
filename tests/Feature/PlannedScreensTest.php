<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlannedScreensTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every navigation entry must resolve — a 404 in the sidebar reads as a
     * broken build rather than unfinished work.
     */
    #[Test]
    public function every_navigation_entry_resolves_for_a_system_admin(): void
    {
        $admin = User::factory()->role(Role::SystemAdmin)->create();

        foreach ([
            '/',
            '/conversations',
            '/usage',
            '/providers',
            '/escalations',
            '/assistants',
            '/integrations',
            '/knowledge',
            '/audit',
            '/alerts',
            '/users',
        ] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    #[Test]
    public function planned_screens_still_enforce_their_permission(): void
    {
        $support = User::factory()->role(Role::SupportAgent)->create();

        $this->actingAs($support)->get('/usage')->assertForbidden();
        $this->actingAs($support)->get('/users')->assertForbidden();

        // موظف الدعم يملك صلاحية عرض التصعيد.
        $this->actingAs($support)->get('/escalations')->assertOk();
    }

    #[Test]
    public function planned_screen_declares_its_phase(): void
    {
        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->get('/knowledge')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Planned/Index')
                ->where('screen.phase', 3)
                ->where('screen.title', 'قاعدة المعرفة'));
    }
}
