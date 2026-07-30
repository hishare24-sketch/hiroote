<?php

declare(strict_types=1);

namespace App\Domains\Administration\Enums;

/**
 * The full permission vocabulary of Hiroote AI.
 *
 * Every gate name in the application MUST come from this enum. A permission
 * that is not listed here does not exist, which keeps the RBAC matrix in
 * `Role::permissions()` exhaustive and auditable (وثيقة 05 §7 — RBAC بأقل صلاحية).
 */
enum Permission: string
{
    // نظرة عامة
    case OverviewView = 'overview.view';

    // المشروع نفسه: إعداده وربطه وعضويته (ADR-0003)
    case ProjectView = 'project.view';
    case ProjectManage = 'project.manage';

    // الأداء والمحادثات
    case ConversationsView = 'conversations.view';
    case ConversationsViewContent = 'conversations.view_content';
    case ConversationsExport = 'conversations.export';

    // نبض المشروع — القيم والمعدّلات اليومية الواردة من المشروع
    case PulseView = 'pulse.view';

    // الاستهلاك والتكلفة
    case UsageView = 'usage.view';
    case UsageManageBudgets = 'usage.manage_budgets';

    // المزودون والنماذج
    case ProvidersView = 'providers.view';
    case ProvidersManage = 'providers.manage';
    case ProvidersManageCredentials = 'providers.manage_credentials';
    case ProvidersFailover = 'providers.failover';

    // إعدادات وسلوك المساعد
    case AssistantsView = 'assistants.view';
    case AssistantsManage = 'assistants.manage';

    // تكامل أقسام المنصة
    case IntegrationsView = 'integrations.view';
    case IntegrationsManage = 'integrations.manage';

    // قاعدة المعرفة
    case KnowledgeView = 'knowledge.view';
    case KnowledgeManage = 'knowledge.manage';
    case KnowledgePublish = 'knowledge.publish';

    // التحويل والتصعيد
    case EscalationsView = 'escalations.view';
    case EscalationsHandle = 'escalations.handle';

    // التنبيهات
    case AlertsView = 'alerts.view';
    case AlertsManage = 'alerts.manage';

    // سجل التشغيل والتدقيق
    case AuditView = 'audit.view';
    case AuditExport = 'audit.export';

    // الصلاحيات والمستخدمون
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';

    // الإعدادات العامة
    case SettingsView = 'settings.view';
    case SettingsManage = 'settings.manage';

    // وضع الصيانة وإيقاف المساعد
    case MaintenanceToggle = 'maintenance.toggle';

    /** ما تعنيه هذه الصلاحية لمن يقرأ المصفوفة — لا اسمها التقني. */
    public function label(): string
    {
        return match ($this) {
            self::OverviewView => 'رؤية النظرة العامة',
            self::ProjectView => 'رؤية إعداد المشروع',
            self::ProjectManage => 'تعديل المشروع وعضويته',
            self::ConversationsView => 'رؤية المحادثات ومؤشراتها',
            self::ConversationsViewContent => 'قراءة نصّ المحادثات',
            self::ConversationsExport => 'تصدير المحادثات',
            self::PulseView => 'رؤية نبض المشروع',
            self::UsageView => 'رؤية الاستهلاك والتكلفة',
            self::UsageManageBudgets => 'ضبط الميزانيات',
            self::ProvidersView => 'رؤية المزودين والنماذج',
            self::ProvidersManage => 'إدارة المزودين',
            self::ProvidersManageCredentials => 'إدارة مفاتيح المزودين',
            self::ProvidersFailover => 'التحويل بين المزودين',
            self::AssistantsView => 'رؤية سلوك المساعد',
            self::AssistantsManage => 'تعديل سلوك المساعد',
            self::IntegrationsView => 'رؤية التكامل والربط',
            self::IntegrationsManage => 'إدارة التكامل والربط',
            self::KnowledgeView => 'رؤية قاعدة المعرفة',
            self::KnowledgeManage => 'تحرير المعرفة والشاشات',
            self::KnowledgePublish => 'نشر المعرفة',
            self::EscalationsView => 'رؤية التصعيد',
            self::EscalationsHandle => 'معالجة التصعيد',
            self::AlertsView => 'رؤية التنبيهات',
            self::AlertsManage => 'إدارة قواعد التنبيه',
            self::AuditView => 'رؤية سجل التدقيق',
            self::AuditExport => 'تصدير سجل التدقيق',
            self::UsersView => 'رؤية المستخدمين',
            self::UsersManage => 'إدارة المستخدمين',
            self::SettingsView => 'رؤية الإعدادات',
            self::SettingsManage => 'تعديل الإعدادات',
            self::MaintenanceToggle => 'وضع الصيانة وإيقاف المساعد',
        };
    }

    /** الشاشة التي تنتمي إليها الصلاحية — لتجميع المصفوفة. */
    public function group(): string
    {
        return match (explode('.', $this->value)[0]) {
            'overview' => 'نظرة عامة',
            'project' => 'المشاريع',
            'conversations' => 'الأداء والمحادثات',
            'pulse' => 'نبض المشروع',
            'usage' => 'الاستهلاك والتكلفة',
            'providers' => 'المزودون والنماذج',
            'assistants' => 'سلوك المساعد',
            'integrations' => 'الربط والتكامل',
            'knowledge' => 'قاعدة المعرفة',
            'escalations' => 'التحويل والتصعيد',
            'alerts' => 'التنبيهات',
            'audit' => 'سجل التشغيل',
            'users' => 'المستخدمون',
            'settings' => 'الإعدادات',
            default => 'أخرى',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
