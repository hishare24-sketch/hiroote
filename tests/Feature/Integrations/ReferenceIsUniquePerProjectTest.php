<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domains\Conversations\Models\Conversation;
use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * مرجع المحادثة فريدٌ **داخل مشروعه** لا في المنصّة كلها.
 *
 * المرجع يولّده المشروع الخارجي من عدّاده هو، فمشروعان يبدآن من `conv-1`
 * أمرٌ عاديّ لا نادر. وفهرسٌ فريد عامّ يجعل الثاني منهما يسقط بانتهاك قيد بدل
 * أن يُنشئ صفّه — عيبٌ لا يظهر إلا حين يتكامل مشروعان، أي عند التوسّع لا عند
 * التجربة.
 */
class ReferenceIsUniquePerProjectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function two_projects_may_send_the_same_reference(): void
    {
        $first = ProjectFactory::default();
        $second = Project::factory()->create(['slug' => 'second-one', 'sort_order' => 9]);

        $payload = [
            'reference' => 'conv-1',
            'outcome' => 'resolved',
            'message_count' => 3,
        ];

        $this->withToken($this->mint($first))
            ->postJson('/api/v1/conversations', $payload)
            ->assertCreated();

        $this->withToken($this->mint($second))
            ->postJson('/api/v1/conversations', $payload)
            ->assertCreated();

        $this->assertSame(2, Conversation::query()->where('reference', 'conv-1')->count());
    }

    #[Test]
    public function resending_within_one_project_still_updates_instead_of_duplicating(): void
    {
        // التفرّد لم يُلغَ بل ضاق إلى المشروع: إعادةُ الإرسال عند فشل شبكة يجب
        // أن تُحدّث لا أن تضاعف كل إحصاء.
        $project = ProjectFactory::default();
        $token = $this->mint($project);

        $this->withToken($token)
            ->postJson('/api/v1/conversations', [
                'reference' => 'conv-7',
                'outcome' => 'open',
                'message_count' => 2,
            ])
            ->assertCreated();

        $this->withToken($token)
            ->postJson('/api/v1/conversations', [
                'reference' => 'conv-7',
                'outcome' => 'resolved',
                'message_count' => 6,
            ])
            ->assertOk();

        $this->assertSame(1, Conversation::query()->where('reference', 'conv-7')->count());
        $this->assertSame(6, Conversation::query()->firstOrFail()->message_count);
    }

    private function mint(Project $project): string
    {
        $minted = ProjectApiKey::mint();

        ProjectApiKey::query()->create([
            'project_id' => $project->id,
            'name' => 'مفتاح',
            'prefix' => $minted['prefix'],
            'hash' => $minted['hash'],
        ]);

        return $minted['token'];
    }
}
