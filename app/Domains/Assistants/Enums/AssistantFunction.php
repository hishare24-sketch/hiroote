<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Enums;

use App\Support\Enums\PresentableEnum;

/**
 * وظائف المساعد — وثيقة 06 §13، كل وظيفة سويتش مستقل.
 *
 * القائمة مغلقة في enum لا مفتوحة في جدول: إضافة وظيفة تعني كودًا ينفّذها، فلا
 * ينبغي أن تُضاف بصفٍّ في قاعدة البيانات وحده ثم تبدو مفعّلة وهي بلا أثر.
 */
enum AssistantFunction: string implements PresentableEnum
{
    case Conversation = 'conversation';
    case ReadData = 'read_data';
    case ExecuteActions = 'execute_actions';
    case ReadFiles = 'read_files';
    case AnalyzeAttachments = 'analyze_attachments';
    case Recommend = 'recommend';
    case Summarize = 'summarize';
    case TrackClicks = 'track_clicks';
    case TrackResolution = 'track_resolution';
    case CreateTickets = 'create_tickets';
    case HumanHandoff = 'human_handoff';
    case EscalationEmail = 'escalation_email';
    case ShowRelatedScreens = 'show_related_screens';
    case ChatZoom = 'chat_zoom';

    public function label(): string
    {
        return match ($this) {
            self::Conversation => 'المحادثة والإجابة',
            self::ReadData => 'استدعاء وعرض البيانات',
            self::ExecuteActions => 'تنفيذ الإجراءات',
            self::ReadFiles => 'قراءة الصور وPDF والملفات',
            self::AnalyzeAttachments => 'تحليل المرفقات',
            self::Recommend => 'الاقتراح والتحليل والتوصيات',
            self::Summarize => 'تلخيص المحتوى',
            self::TrackClicks => 'تتبع نقرات الشاشات',
            self::TrackResolution => 'تتبع نتيجة الحل',
            self::CreateTickets => 'إنشاء التذاكر',
            self::HumanHandoff => 'التحويل البشري',
            self::EscalationEmail => 'إرسال بريد التصعيد',
            self::ShowRelatedScreens => 'عرض روابط وشاشات مرتبطة بالإجابة',
            self::ChatZoom => 'تكبير واجهة شات المساعد',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Conversation => 'الأساس — إطفاؤه يوقف المساعد كليًا عن الرد.',
            self::ReadData => 'قراءة بيانات المستخدم من Hi-Share عبر REST لعرضها في الرد.',
            self::ExecuteActions => 'تنفيذ إجراء نيابةً عن المستخدم، لا مجرد وصفه.',
            self::ReadFiles => 'فتح الصور وملفات PDF المرفقة واستخراج نصّها.',
            self::AnalyzeAttachments => 'تحليل محتوى المرفق والبناء عليه في الرد.',
            self::Recommend => 'اقتراح خطوة تالية دون أن يطلبها المستخدم صراحةً.',
            self::Summarize => 'تلخيص محتوى طويل قبل عرضه.',
            self::TrackClicks => 'تسجيل الشاشات التي فتحها المستخدم من الرد.',
            self::TrackResolution => 'ربط النقرة بالحل لقياس نسبة الحل الفعلية.',
            self::CreateTickets => 'فتح تذكرة تلقائيًا حين يتعذّر الحل.',
            self::HumanHandoff => 'تسليم المحادثة إلى موظف بشري.',
            self::EscalationEmail => 'إشعار المسؤولين بالبريد عند التصعيد الحرج.',
            self::ShowRelatedScreens => 'إرفاق روابط شاشات التطبيق بالإجابة.',
            self::ChatZoom => 'توسيع نافذة الشات داخل تطبيق Hi-Share.',
        };
    }

    /**
     * الوظائف التي يعتمد أثرها على وظيفة أخرى — تُعطَّل في الواجهة حين تُطفأ أصلها.
     */
    public function dependsOn(): ?self
    {
        return match ($this) {
            self::AnalyzeAttachments => self::ReadFiles,
            self::TrackResolution => self::TrackClicks,
            self::EscalationEmail => self::HumanHandoff,
            default => null,
        };
    }

    /**
     * وظيفة حساسة تحتاج قرارًا واعيًا — تبدأ مطفأة ويُنبَّه عليها في الواجهة.
     */
    public function isSensitive(): bool
    {
        return match ($this) {
            self::ExecuteActions, self::EscalationEmail => true,
            default => false,
        };
    }

    /**
     * معنى «زوم شات المساعد» لم يُثبَّت بعد (وثيقة 06 §21).
     *
     * تظهر الوظيفة معلّمة ومعطّلة بدل تخمين معنًى قد يكون خاطئًا: سويتش يعمل
     * على تعريف مجهول أسوأ من سويتش موقوف بسبب معلن.
     */
    public function awaitsDecision(): bool
    {
        return $this === self::ChatZoom;
    }

    public function defaultEnabled(): bool
    {
        return ! $this->isSensitive() && ! $this->awaitsDecision();
    }

    public function tone(): string
    {
        return $this->isSensitive() ? 'warning' : 'accent';
    }
}
