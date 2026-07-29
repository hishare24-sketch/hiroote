<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * كل بند في القائمة الجانبية يفتح شاشةً حقيقية.
     *
     * لم يعد في اللوحة بندٌ «مخطَّط»: المستخدمون كانوا آخره، وحُذفت معه آليةُ
     * الشاشة المخطَّطة كلها — صفحةٌ ومتحكّمٌ وشرحٌ لا يصل إليها أحد.
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
            '/bridge',
            '/projects',
            '/users',
        ] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    #[Test]
    public function each_entry_still_enforces_its_permission(): void
    {
        $support = User::factory()->role(Role::SupportAgent)->create();

        $this->actingAs($support)->get('/usage')->assertForbidden();
        $this->actingAs($support)->get('/users')->assertForbidden();

        // موظف الدعم يملك صلاحية عرض التصعيد.
        $this->actingAs($support)->get('/escalations')->assertOk();
    }
}
