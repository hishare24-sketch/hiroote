<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * الفلاتر الزمنية العامة — وثيقة 06 §5.
 *
 * تُحسب هنا مرة واحدة لكل الشاشات، ومعها الفترة السابقة المماثلة حتى تكون
 * المقارنة على مدى متساوٍ لا على «الشهر الماضي» تقريبًا.
 */
final readonly class Period
{
    private function __construct(
        public string $key,
        public string $label,
        public Carbon $from,
        public Carbon $to,
    ) {}

    /** @var array<string, string> */
    public const OPTIONS = [
        'hour' => 'آخر ساعة',
        'today' => 'اليوم',
        'week' => 'آخر 7 أيام',
        'month' => 'آخر 30 يومًا',
        'current_month' => 'الشهر الحالي',
        'custom' => 'من تاريخ إلى تاريخ',
    ];

    public static function fromRequest(Request $request): self
    {
        $key = $request->string('period')->value();
        $from = $request->date('from');
        $to = $request->date('to');

        if ($key === 'custom' && $from !== null && $to !== null) {
            return new self(
                'custom',
                self::OPTIONS['custom'],
                Carbon::instance($from)->startOfDay(),
                Carbon::instance($to)->endOfDay(),
            );
        }

        // آخر 30 يومًا هو المدى الافتراضي: يغطي دورة فوترة كاملة تقريبًا.
        $key = array_key_exists($key, self::OPTIONS) && $key !== 'custom' ? $key : 'month';
        $now = Carbon::now();

        $from = match ($key) {
            'hour' => $now->copy()->subHour(),
            'today' => $now->copy()->startOfDay(),
            'week' => $now->copy()->subDays(7)->startOfDay(),
            'current_month' => $now->copy()->startOfMonth(),
            default => $now->copy()->subDays(30)->startOfDay(),
        };

        return new self($key, self::OPTIONS[$key], $from, $now);
    }

    /** الفترة السابقة المماثلة — نفس الطول تمامًا، منتهية عند بداية الحالية. */
    public function previous(): self
    {
        $length = $this->from->diffInSeconds($this->to);

        return new self(
            $this->key,
            'الفترة السابقة',
            $this->from->copy()->subSeconds((int) $length),
            $this->from->copy(),
        );
    }

    public function days(): int
    {
        return max(1, (int) $this->from->diffInDays($this->to) + 1);
    }

    /** @return array{key: string, label: string, from: string, to: string} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'from' => $this->from->toIso8601String(),
            'to' => $this->to->toIso8601String(),
        ];
    }
}
