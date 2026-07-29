<?php

declare(strict_types=1);

namespace App\Support\Help;

use App\Domains\Administration\Enums\Role;

/**
 * شرح شاشة واحدة للمشغّل.
 *
 * `purpose` يجيب «لماذا هذه الشاشة موجودة»، و`reading` يجيب «كيف أقرأ ما فيها»،
 * و`traps` يجيب «ما الذي سيضللني إن لم أنتبه». الثالث هو الأهم: الشاشة التي
 * تُشرح بلا ذكر ما يُساء فهمه فيها تُقرأ خطأً بثقة.
 */
final readonly class HelpTopic
{
    /**
     * @param  list<array{heading: string, body: string}>  $reading
     * @param  list<string>  $traps
     * @param  array<string, string>  $forRole  ملاحظة تخص دورًا بعينه
     */
    public function __construct(
        public string $screen,
        public string $title,
        public string $purpose,
        public array $reading = [],
        public array $traps = [],
        public array $forRole = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payload(?Role $role): array
    {
        return [
            'screen' => $this->screen,
            'title' => $this->title,
            'purpose' => $this->purpose,
            'reading' => $this->reading,
            'traps' => $this->traps,
            // ملاحظة الدور وحدها تُرسل: ما يخصّ محلل التكلفة لا يعني موظف الدعم،
            // وعرض الكل يحوّل الشرح إلى نصٍّ يُتخطّى.
            'role_note' => $role === null ? null : ($this->forRole[$role->value] ?? null),
            'role_label' => $role?->label(),
        ];
    }
}
