<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\DTOs;

use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Models\KnowledgeScreen;

/**
 * التعليمات المبنيّة، ومعها **هل كان للمساعد مرجعٌ أصلًا**.
 *
 * السؤال الثاني لا يُستنتج من نصّ الرد: مساعدٌ بلا معرفةٍ منشورة أُمر أن يقول
 * «لا أعرف»، وطاعتُه للأمر ليست حلًّا. ومحادثةٌ كهذه تُسجَّل «تم الحل» ترفع
 * نسبةَ الحل — وهي أكثر رقمٍ يُقرأ في اللوحة — عن معرفةٍ لم تُكتب بعد.
 */
final readonly class AssembledContext
{
    public function __construct(
        public string $system,
        /** هل عبر إلى التعليمات عنصرُ معرفةٍ منشور واحد على الأقل. */
        public bool $hasReference,
        public ?KnowledgeScreen $screen = null,
        public ?ProjectSection $section = null,
    ) {}
}
