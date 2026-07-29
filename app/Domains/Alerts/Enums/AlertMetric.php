<?php

declare(strict_types=1);

namespace App\Domains\Alerts\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * ما يمكن للقاعدة أن تراقبه — وثيقة 06 §11 («الحدث أو المؤشر»).
 *
 * كل مؤشر هنا يُحسب من بيانات موجودة فعلًا في اللوحة، فلا قاعدة تنتظر نظامًا
 * لم يُبنَ بعد. ما لا يمكن قياسه اليوم لا يُعرض خيارًا: قاعدة لا تُقيَّم أسوأ
 * من غياب القاعدة، لأن المشغّل يظن أن أحدًا يراقب.
 */
enum AlertMetric: string implements PresentableEnum
{
    case EscalationRate = 'escalation_rate';
    case AbandonRate = 'abandon_rate';
    case UnresolvedRate = 'unresolved_rate';
    case LowConfidenceRate = 'low_confidence_rate';
    case AvgResponseMs = 'avg_response_ms';
    case ConversationVolume = 'conversation_volume';
    case CostTotal = 'cost_total';
    case TokensTotal = 'tokens_total';
    case AvgRating = 'avg_rating';
    case ProviderErrorRate = 'provider_error_rate';
    case ProviderBalance = 'provider_balance';
    case OpenKnowledgeNotes = 'open_knowledge_notes';

    public function label(): string
    {
        return match ($this) {
            self::EscalationRate => 'نسبة التحويل إلى بشري',
            self::AbandonRate => 'نسبة المحادثات المنقطعة',
            self::UnresolvedRate => 'نسبة غير المحلولة',
            self::LowConfidenceRate => 'نسبة الثقة المنخفضة',
            self::AvgResponseMs => 'متوسط زمن الرد',
            self::ConversationVolume => 'عدد المحادثات',
            self::CostTotal => 'التكلفة',
            self::TokensTotal => 'الرموز المستهلكة',
            self::AvgRating => 'متوسط التقييم',
            self::ProviderErrorRate => 'أعلى معدل أخطاء لمزود',
            self::ProviderBalance => 'أدنى رصيد مزود',
            self::OpenKnowledgeNotes => 'ملاحظات معرفة مفتوحة',
        };
    }

    public function tone(): string
    {
        return $this->family()->tone();
    }

    /** الشرح الذي يظهر تحت الخيار في منشئ القاعدة. */
    public function hint(): string
    {
        return match ($this) {
            self::EscalationRate => 'نسبة المحادثات التي انتهت إلى موظف بشري خلال الفترة.',
            self::AbandonRate => 'نسبة من غادر قبل الحصول على إجابة.',
            self::UnresolvedRate => 'ما لم ينتهِ بالحل: تذكرة أو تحويل أو انقطاع.',
            self::LowConfidenceRate => 'نسبة المحادثات التي قلّت ثقة المساعد فيها عن ٦٠٪.',
            self::AvgResponseMs => 'متوسط زمن الرد بالمللي ثانية.',
            self::ConversationVolume => 'عدد المحادثات في الفترة — يفيد لرصد الهبوط لا الارتفاع فقط.',
            self::CostTotal => 'مجموع التكلفة المحتسبة في الفترة.',
            self::TokensTotal => 'مجموع الرموز المستهلكة في الفترة.',
            self::AvgRating => 'متوسط تقييم المستخدمين من خمسة.',
            self::ProviderErrorRate => 'أعلى معدل أخطاء مسجَّل على مزود مفعَّل.',
            self::ProviderBalance => 'أقل رصيد متبقٍّ بين المزودين المفعَّلين.',
            self::OpenKnowledgeNotes => 'أسئلة بلا إجابة واقتراحات لم تُعالج.',
        };
    }

    public function unit(): MetricUnit
    {
        return match ($this) {
            self::EscalationRate, self::AbandonRate,
            self::UnresolvedRate, self::LowConfidenceRate,
            self::ProviderErrorRate => MetricUnit::Percent,
            self::AvgResponseMs => MetricUnit::Milliseconds,
            self::CostTotal, self::ProviderBalance => MetricUnit::Money,
            self::AvgRating => MetricUnit::Rating,
            default => MetricUnit::Count,
        };
    }

    public function family(): MetricFamily
    {
        return match ($this) {
            self::EscalationRate, self::AbandonRate, self::UnresolvedRate,
            self::LowConfidenceRate, self::AvgResponseMs,
            self::ConversationVolume, self::AvgRating => MetricFamily::Conversations,
            self::CostTotal, self::TokensTotal => MetricFamily::Cost,
            self::ProviderErrorRate, self::ProviderBalance => MetricFamily::Providers,
            self::OpenKnowledgeNotes => MetricFamily::Knowledge,
        };
    }

    /**
     * هل تحدّه نافذة زمنية.
     *
     * الرصيد ومعدل الأخطاء وعدد الملاحظات المفتوحة حالاتٌ راهنة لا مجاميع
     * فترة: «رصيد آخر ساعة» جملة بلا معنى، وعرض حقل الفترة معها يوهم بضبطٍ
     * لا أثر له.
     */
    public function isWindowed(): bool
    {
        return match ($this) {
            self::ProviderErrorRate, self::ProviderBalance, self::OpenKnowledgeNotes => false,
            default => true,
        };
    }

    /** هل يُقيَّد هذا المؤشر بأقسام بعينها. */
    public function supportsSectionScope(): bool
    {
        return $this->family() === MetricFamily::Conversations
            || $this->family() === MetricFamily::Knowledge;
    }

    /** الشرط الأكثر منطقية لهذا المؤشر — يُقترح في المنشئ ولا يُفرض. */
    public function suggestedComparison(): AlertComparison
    {
        return match ($this) {
            self::AvgRating, self::ProviderBalance,
            self::ConversationVolume => AlertComparison::LessThan,
            default => AlertComparison::GreaterThan,
        };
    }

    public function suggestedThreshold(): float
    {
        return match ($this) {
            self::EscalationRate => 20,
            self::AbandonRate => 15,
            self::UnresolvedRate => 30,
            self::LowConfidenceRate => 25,
            self::AvgResponseMs => 4000,
            self::ConversationVolume => 10,
            self::CostTotal => 500,
            self::TokensTotal => 1_000_000,
            self::AvgRating => 3.5,
            self::ProviderErrorRate => 5,
            self::ProviderBalance => 100,
            self::OpenKnowledgeNotes => 10,
        };
    }
}
