<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Providers\Models\AiProvider;
use Illuminate\Database\Seeder;

/**
 * المزودان الافتراضيان (وثيقة 01 §10 — مزودان على الأقل مع Failover).
 *
 * لا مفاتيح هنا إطلاقًا: تُدخل المفاتيح من شاشة المزودين وتحفظ مشفرة.
 * الأسعار لكل مليون توكن بالدولار وتُراجع دوريًا من صفحات أسعار المزودين.
 */
class ProvidersSeeder extends Seeder
{
    public function run(): void
    {
        $openai = AiProvider::query()->firstOrCreate(
            ['slug' => 'openai'],
            [
                'name' => 'OpenAI',
                'base_url' => 'https://api.openai.com/v1',
                'priority' => 1,
                'is_enabled' => true,
                'is_active' => true,
            ],
        );

        $openai->models()->firstOrCreate(
            ['name' => 'gpt-4o'],
            [
                'display_name' => 'GPT-4o',
                'is_default' => true,
                'input_cost_per_million' => '2.5000',
                'output_cost_per_million' => '10.0000',
            ],
        );

        $openai->models()->firstOrCreate(
            ['name' => 'gpt-4o-mini'],
            [
                'display_name' => 'GPT-4o mini',
                'input_cost_per_million' => '0.1500',
                'output_cost_per_million' => '0.6000',
            ],
        );

        $anthropic = AiProvider::query()->firstOrCreate(
            ['slug' => 'anthropic'],
            [
                'name' => 'Anthropic',
                'base_url' => 'https://api.anthropic.com/v1',
                'priority' => 2,
                'is_enabled' => true,
                'is_active' => false,
            ],
        );

        $anthropic->models()->firstOrCreate(
            ['name' => 'claude-sonnet-4-5'],
            [
                'display_name' => 'Claude Sonnet 4.5',
                'is_default' => true,
                'input_cost_per_million' => '3.0000',
                'output_cost_per_million' => '15.0000',
            ],
        );

        $anthropic->models()->firstOrCreate(
            ['name' => 'claude-haiku-4-5'],
            [
                'display_name' => 'Claude Haiku 4.5',
                'input_cost_per_million' => '1.0000',
                'output_cost_per_million' => '5.0000',
            ],
        );
    }
}
