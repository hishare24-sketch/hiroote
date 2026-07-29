<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\Contracts;

use App\Domains\Orchestrator\DTOs\DriverReply;
use App\Domains\Providers\Models\AiModel;
use App\Domains\Providers\Models\AiProvider;

/**
 * مهايئ مزودٍ واحد — **الموضع الوحيد في المشروع الذي ينادي ذكاءً خارجيًّا**
 * (CLAUDE.md رقم ٢، وثيقة 02 §4).
 *
 * الأصناف المنفِّذة لا تُنادى مباشرةً من متحكّم ولا من إجراء: `RunAssistant`
 * وحدها تحلّها من السجل، لأن كل نداء يجب أن يمرّ بالمحاسبة والتحويل والتسجيل.
 */
interface AiDriver
{
    /** المزود الذي يخدمه هذا المهايئ — يطابق `AiProvider::$slug`. */
    public function slug(): string;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(
        AiProvider $provider,
        AiModel $model,
        string $apiKey,
        string $system,
        array $messages,
        int $maxTokens,
        float $temperature,
    ): DriverReply;
}
