<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\Services;

use App\Domains\Orchestrator\Contracts\AiDriver;
use App\Domains\Orchestrator\Drivers\AnthropicDriver;
use App\Domains\Orchestrator\Drivers\OpenAiDriver;
use App\Domains\Providers\Models\AiProvider;

/**
 * يحلّ مهايئ المزود من نبذته.
 *
 * مزودٌ بلا مهايئ **يُعلَن ولا يُخمَّن**: تشغيلُه على مهايئ مزودٍ آخر يرسل
 * جسمًا لا يفهمه فيُقرأ الإخفاق عطلَ شبكة.
 */
class DriverRegistry
{
    /** @var array<string, AiDriver> */
    private array $drivers = [];

    public function __construct(AnthropicDriver $anthropic, OpenAiDriver $openai)
    {
        foreach ([$anthropic, $openai] as $driver) {
            $this->drivers[$driver->slug()] = $driver;
        }
    }

    public function for(AiProvider $provider): ?AiDriver
    {
        return $this->drivers[$provider->slug] ?? null;
    }

    /** @return list<string> */
    public function supported(): array
    {
        return array_keys($this->drivers);
    }
}
