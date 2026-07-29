<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\DTOs;

use App\Domains\Conversations\Enums\AssistantLevel;
use App\Domains\Projects\Models\Project;

/**
 * طلبٌ واحد إلى المساعد — ما يدخل الطبقة، لا ما يخرج إلى المزود.
 *
 * `screenKey` هو ما يجعل الأثر قابلًا للقياس لاحقًا: بدونه تُسجَّل المحادثة
 * بلا موضع، فيبقى سؤال «هل نفع تعديل وصف هذه الشاشة؟» بلا جواب.
 */
final readonly class AssistantRequest
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function __construct(
        public Project $project,
        public array $messages,
        public ?string $sectionName = null,
        public ?string $screenKey = null,
        public AssistantLevel $level = AssistantLevel::Balanced,
        public ?string $userLabel = null,
        public ?string $externalUserId = null,
        public ?string $reference = null,
    ) {}

    public function lastUserMessage(): string
    {
        foreach (array_reverse($this->messages) as $message) {
            if ($message['role'] === 'user') {
                return $message['content'];
            }
        }

        return '';
    }
}
