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

    // الأداء والمحادثات
    case ConversationsView = 'conversations.view';
    case ConversationsViewContent = 'conversations.view_content';
    case ConversationsExport = 'conversations.export';

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

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
