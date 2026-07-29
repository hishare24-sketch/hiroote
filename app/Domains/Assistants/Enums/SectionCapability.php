<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * أعمدة مصفوفة تكامل أقسام المشروع — وثيقة 06 §14.
 *
 * قدرةٌ لكل عمود، تُفتح أو تُغلق لكل قسم على حدة: القسم قد يُقرأ منه ولا
 * يُنفَّذ فيه إجراء، وقسمٌ آخر يُنشئ تذاكر ولا يحوّل بشريًا.
 */
enum SectionCapability: string implements PresentableEnum
{
    case AiEnabled = 'ai_enabled';
    case Knowledge = 'knowledge';
    case DatabaseLink = 'database_link';
    case ApiCall = 'api_call';
    case ShowData = 'show_data';
    case SuggestAction = 'suggest_action';
    case ExecuteAction = 'execute_action';
    case ReadFiles = 'read_files';
    case CreateTicket = 'create_ticket';
    case HumanHandoff = 'human_handoff';
    case Feedback = 'feedback';

    public function label(): string
    {
        return match ($this) {
            self::AiEnabled => 'تفعيل الذكاء',
            self::Knowledge => 'معرفة',
            self::DatabaseLink => 'ربط قاعدة البيانات',
            self::ApiCall => 'استدعاء API',
            self::ShowData => 'عرض البيانات',
            self::SuggestAction => 'اقتراح إجراء',
            self::ExecuteAction => 'تنفيذ إجراء',
            self::ReadFiles => 'قراءة الملفات',
            self::CreateTicket => 'إنشاء تذكرة',
            self::HumanHandoff => 'تحويل بشري',
            self::Feedback => 'التغذية الراجعة',
        };
    }

    /** الاختصار المعروض في ترويسة المصفوفة الضيقة. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::AiEnabled => 'الذكاء',
            self::Knowledge => 'معرفة',
            self::DatabaseLink => 'قاعدة',
            self::ApiCall => 'API',
            self::ShowData => 'عرض',
            self::SuggestAction => 'اقتراح',
            self::ExecuteAction => 'تنفيذ',
            self::ReadFiles => 'ملفات',
            self::CreateTicket => 'تذكرة',
            self::HumanHandoff => 'بشري',
            self::Feedback => 'تقييم',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AiEnabled => 'المفتاح الرئيسي للقسم — إطفاؤه يعطّل بقية قدراته كلها.',
            self::Knowledge => 'للقسم عناصر معرفة يبني عليها المساعد إجاباته.',
            self::DatabaseLink => 'ربط حقول القسم بمخطط بيانات المشروع لفهم معانيها.',
            self::ApiCall => 'استدعاء REST الخاص بالمشروع لقراءة بيانات حيّة.',
            self::ShowData => 'عرض البيانات المقروءة داخل الرد لا وصفها فقط.',
            self::SuggestAction => 'اقتراح خطوة تالية على المستخدم دون تنفيذها.',
            self::ExecuteAction => 'تنفيذ الإجراء نيابةً عن المستخدم — قدرة حساسة.',
            self::ReadFiles => 'فتح مرفقات القسم وقراءتها.',
            self::CreateTicket => 'فتح تذكرة تلقائية حين يتعذّر الحل في القسم.',
            self::HumanHandoff => 'تسليم محادثات القسم إلى موظف بشري.',
            self::Feedback => 'جمع تقييم المستخدم لإجابات هذا القسم.',
        };
    }

    /**
     * القدرة التي لا تعمل هذه بدونها.
     *
     * تُعطَّل في الواجهة حين يُطفأ أصلها بدل أن تبدو مفعّلة وهي بلا أثر.
     */
    public function dependsOn(): ?self
    {
        return match ($this) {
            self::ShowData => self::ApiCall,
            self::ExecuteAction => self::SuggestAction,
            default => null,
        };
    }

    /** قدرة تغيّر بيانات المستخدم أو تلتزم نيابةً عنه — تبدأ مطفأة. */
    public function isSensitive(): bool
    {
        return $this === self::ExecuteAction;
    }

    public function defaultEnabled(): bool
    {
        return match ($this) {
            self::ExecuteAction, self::ReadFiles => false,
            default => true,
        };
    }

    public function tone(): string
    {
        return $this->isSensitive() ? 'warning' : 'accent';
    }

    /** @return list<self> */
    public static function toggleable(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $capability): bool => $capability !== self::AiEnabled,
        ));
    }
}
