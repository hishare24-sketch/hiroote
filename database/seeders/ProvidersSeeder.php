<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Providers\Enums\ProviderStatus;
use App\Domains\Providers\Models\AiProvider;
use Illuminate\Database\Seeder;

/**
 * المزودون الثلاثة كما في التصميم المعتمد (شاشة المزودين والنماذج).
 *
 * لا مفاتيح هنا إطلاقًا: تُدخل المفاتيح من الشاشة وتحفظ مشفرة.
 * أرقام الرصيد ومعدل الاستهلاك قيم ابتدائية للتطوير — تُستبدل بقيم فعلية
 * من محرك التكلفة في المرحلة الثانية.
 */
class ProvidersSeeder extends Seeder
{
    /**
     * @var list<array{
     *     slug: string, name: string, base_url: string, priority: int, active: bool,
     *     balance: string, burn: string, latency: int, errors: string,
     *     models: list<array{name: string, display: string, default?: bool, in: string, out: string}>
     * }>
     */
    private const PROVIDERS = [
        [
            'slug' => 'openai',
            'name' => 'OpenAI',
            'base_url' => 'https://api.openai.com/v1',
            'priority' => 1,
            'active' => true,
            'balance' => '1890.00',
            'burn' => '4.2000',
            'latency' => 1800,
            'errors' => '0.70',
            'models' => [
                ['name' => 'gpt-5.1', 'display' => 'GPT-5.1', 'default' => true, 'in' => '2.5000', 'out' => '10.0000'],
                ['name' => 'gpt-5.1-mini', 'display' => 'GPT-5.1 mini', 'in' => '0.1500', 'out' => '0.6000'],
            ],
        ],
        [
            'slug' => 'google',
            'name' => 'Google',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'priority' => 2,
            'active' => false,
            'balance' => '3450.00',
            'burn' => '3.1000',
            'latency' => 2300,
            'errors' => '1.10',
            'models' => [
                ['name' => 'gemini-2.5-pro', 'display' => 'Gemini 2.5 Pro', 'default' => true, 'in' => '1.2500', 'out' => '5.0000'],
                ['name' => 'gemini-2.5-flash', 'display' => 'Gemini 2.5 Flash', 'in' => '0.3000', 'out' => '2.5000'],
            ],
        ],
        [
            'slug' => 'anthropic',
            'name' => 'Anthropic',
            'base_url' => 'https://api.anthropic.com/v1',
            'priority' => 3,
            'active' => false,
            'balance' => '2710.00',
            'burn' => '3.8000',
            'latency' => 2000,
            'errors' => '0.90',
            'models' => [
                ['name' => 'claude-sonnet-4-5', 'display' => 'Claude Sonnet', 'default' => true, 'in' => '3.0000', 'out' => '15.0000'],
                ['name' => 'claude-haiku-4-5', 'display' => 'Claude Haiku', 'in' => '1.0000', 'out' => '5.0000'],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::PROVIDERS as $definition) {
            $provider = AiProvider::query()->firstOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'base_url' => $definition['base_url'],
                    'priority' => $definition['priority'],
                    'is_enabled' => true,
                    'is_active' => $definition['active'],
                    'status' => ProviderStatus::Unknown,
                    'balance' => $definition['balance'],
                    'burn_rate_per_minute' => $definition['burn'],
                    'currency' => 'SAR',
                    'avg_latency_ms' => $definition['latency'],
                    'error_rate' => $definition['errors'],
                ],
            );

            foreach ($definition['models'] as $model) {
                $provider->models()->firstOrCreate(
                    ['name' => $model['name']],
                    [
                        'display_name' => $model['display'],
                        'is_default' => $model['default'] ?? false,
                        'input_cost_per_million' => $model['in'],
                        'output_cost_per_million' => $model['out'],
                    ],
                );
            }
        }
    }
}
