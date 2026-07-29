<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Models\User;
use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Models\AiProviderCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProviderCredentialTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function storing_a_credential_encrypts_it_at_rest(): void
    {
        $provider = AiProvider::factory()->create();
        $manager = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($manager)
            ->post("/providers/{$provider->id}/credentials", [
                'label' => 'مفتاح الإنتاج',
                'api_key' => 'sk-test-1234567890abcd',
            ])
            ->assertRedirect();

        $credential = AiProviderCredential::query()->sole();

        // القيمة في القاعدة مشفرة — لا تساوي النص الصريح.
        $raw = DB::table('ai_provider_credentials')->value('api_key');
        $this->assertIsString($raw);
        $this->assertStringNotContainsString('sk-test', $raw);

        // لكن الموديل يفكها لمن يملك APP_KEY.
        $this->assertSame('sk-test-1234567890abcd', $credential->api_key);
        $this->assertSame('abcd', $credential->key_hint);
    }

    #[Test]
    public function new_credential_deactivates_previous_ones(): void
    {
        $provider = AiProvider::factory()->create();
        $manager = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($manager)->post("/providers/{$provider->id}/credentials", [
            'label' => 'الأول',
            'api_key' => 'sk-first-11111111',
        ]);

        $this->actingAs($manager)->post("/providers/{$provider->id}/credentials", [
            'label' => 'الثاني',
            'api_key' => 'sk-second-22222222',
        ]);

        $this->assertSame(1, $provider->credentials()->where('is_active', true)->count());
        $this->assertSame('الثاني', $provider->activeCredential()?->label);
    }

    #[Test]
    public function audit_entry_never_contains_the_key_itself(): void
    {
        $provider = AiProvider::factory()->create();
        $manager = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($manager)->post("/providers/{$provider->id}/credentials", [
            'label' => 'مفتاح',
            'api_key' => 'sk-supersecret-xyz9',
        ]);

        $entry = AuditLog::query()->forAction('providers.credential_stored')->sole();
        $serialized = json_encode($entry->new_values);

        $this->assertIsString($serialized);
        $this->assertStringNotContainsString('supersecret', $serialized);
        $this->assertStringContainsString('xyz9', $serialized);
    }

    #[Test]
    public function revoking_soft_deletes_and_audits(): void
    {
        $provider = AiProvider::factory()->create();
        $manager = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($manager)->post("/providers/{$provider->id}/credentials", [
            'label' => 'مفتاح',
            'api_key' => 'sk-revoke-me-0000',
        ]);

        $credential = AiProviderCredential::query()->sole();

        $this->actingAs($manager)
            ->delete("/providers/{$provider->id}/credentials/{$credential->id}")
            ->assertRedirect();

        $this->assertSoftDeleted('ai_provider_credentials', ['id' => $credential->id]);
        $this->assertNull($provider->activeCredential());
        $this->assertSame(1, AuditLog::query()->forAction('providers.credential_revoked')->count());
    }

    #[Test]
    public function support_agent_cannot_store_credentials(): void
    {
        $provider = AiProvider::factory()->create();
        $support = User::factory()->role(Role::SupportAgent)->create();

        $this->actingAs($support)
            ->post("/providers/{$provider->id}/credentials", [
                'label' => 'x',
                'api_key' => 'sk-nope-12345678',
            ])
            ->assertForbidden();
    }
}
