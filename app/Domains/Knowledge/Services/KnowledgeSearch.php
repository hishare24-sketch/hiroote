<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Services;

use App\Domains\Knowledge\Models\KnowledgeItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * قاعدة المطابقة — **واحدة** للمحرِّر وللمساعد.
 *
 * محرِّرٌ يبحث بقاعدة والمساعدُ يختار بأخرى يقرأ نتيجةً لا تصف ما يجري: يجد
 * العنصر في الشاشة، ويسمع من المستخدم أن المساعد لا يعرفه، فيبحث عن العطل في
 * المعرفة وهو في اختلاف القاعدتين.
 *
 * والمطابقة نصّية لا دلالية: «سحب» لا تطابق «صرف». وهذا حدٌّ معلوم يُقال في
 * الشاشة، لا عيبٌ يُكتشف حين لا يجد أحدٌ ما يعرف أنه مكتوب.
 */
class KnowledgeSearch
{
    /** ثمانيةٌ بطول ثلاثة فأكثر: الأقصر يطابق كل شيء فلا يميّز، والأكثر يُعيد الكل. */
    public const MAX_TERMS = 8;

    private const MIN_LENGTH = 3;

    /**
     * @param  Builder<KnowledgeItem>  $query
     * @return Builder<KnowledgeItem>
     */
    public function apply(Builder $query, string $question): Builder
    {
        $terms = $this->terms($question);

        if ($terms === []) {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($terms): void {
            foreach ($terms as $term) {
                $inner->orWhere('title', 'ilike', "%{$term}%")
                    ->orWhere('summary', 'ilike', "%{$term}%")
                    ->orWhere('body', 'ilike', "%{$term}%");
            }
        });
    }

    /**
     * كلمات السؤال الصالحة للبحث.
     *
     * الحروف والأرقام والعلامات المركّبة وحدها — لا علامات الترقيم.
     * و**علامة الاستفهام العربية `؟` والفاصلة `،` داخل نطاق `\p{Arabic}`**،
     * فكان `\p{Arabic}` يلصقها بالكلمة: يصير المصطلح «المشاريع؟» ولا يطابق
     * «المشاريع» في أي نصّ. أي أن آخر كلمة في كل سؤال — وهي غالبًا أهمّها —
     * كانت تُهدر صامتة، في الشاشة وفي اختيار المساعد معًا.
     *
     * و`\p{M}` لازمة مع `\p{L}`: الشدّة والحركات علاماتٌ لا حروف، فبدونها
     * تنقطع «حدّ» عند الشدّة فتصير حرفين وتسقط.
     *
     * @return list<string>
     */
    public function terms(string $question): array
    {
        preg_match_all('/[\p{L}\p{M}\p{N}]{'.self::MIN_LENGTH.',}/u', $question, $matches);

        return array_slice(array_values(array_unique($matches[0])), 0, self::MAX_TERMS);
    }

    /**
     * مقتطفٌ حول أول موضع مطابقة — لا أول النص.
     *
     * أولُ النص يتشابه في كل العناصر (مقدّمةٌ واحدة)، فتُقرأ النتائج متطابقةً
     * ويُفتح كلٌّ منها ليُعرف أيّها المقصود.
     */
    public function excerpt(string $body, string $question, int $length = 180): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? $body);

        if (mb_strlen($body) <= $length) {
            return $body;
        }

        $at = 0;

        foreach ($this->terms($question) as $term) {
            $position = mb_stripos($body, $term);

            if ($position !== false) {
                $at = max(0, $position - 40);
                break;
            }
        }

        $slice = mb_substr($body, $at, $length);

        return ($at > 0 ? '…' : '').$slice.($at + $length < mb_strlen($body) ? '…' : '');
    }
}
