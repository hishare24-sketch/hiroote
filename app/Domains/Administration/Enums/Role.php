<?php

declare(strict_types=1);

namespace App\Domains\Administration\Enums;

/**
 * The admin roles listed in وثيقة 01 §4.
 *
 * The matrix in `permissions()` is deliberately written out in full instead of
 * granting a wildcard to SystemAdmin: an explicit list is what makes a
 * least-privilege claim reviewable, and it fails closed when a new permission
 * is added without a conscious decision about who receives it.
 */
enum Role: string
{
    case SystemAdmin = 'system_admin';
    case AiManager = 'ai_manager';
    case KnowledgeManager = 'knowledge_manager';
    case CostAnalyst = 'cost_analyst';
    case SupportAgent = 'support_agent';
    case SecurityAuditor = 'security_auditor';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdmin => 'مدير النظام',
            self::AiManager => 'مدير الذكاء الاصطناعي',
            self::KnowledgeManager => 'مدير المعرفة',
            self::CostAnalyst => 'محلل الأداء والتكلفة',
            self::SupportAgent => 'فريق الدعم',
            self::SecurityAuditor => 'فريق الأمن والمراجعة',
        };
    }

    /**
     * @return list<Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SystemAdmin => Permission::cases(),

            self::AiManager => [
                Permission::OverviewView,
                Permission::ProjectView,
                Permission::ConversationsView,
                Permission::ConversationsViewContent,
                Permission::ConversationsExport,
                Permission::PulseView,
                Permission::UsageView,
                Permission::UsageManageBudgets,
                Permission::ProvidersView,
                Permission::ProvidersManage,
                Permission::ProvidersManageCredentials,
                Permission::ProvidersFailover,
                Permission::AssistantsView,
                Permission::AssistantsManage,
                Permission::IntegrationsView,
                Permission::IntegrationsManage,
                Permission::KnowledgeView,
                Permission::EscalationsView,
                Permission::AlertsView,
                Permission::AlertsManage,
                Permission::AuditView,
                Permission::SettingsView,
                Permission::MaintenanceToggle,
            ],

            self::KnowledgeManager => [
                Permission::OverviewView,
                Permission::ConversationsView,
                Permission::ConversationsViewContent,
                Permission::KnowledgeView,
                Permission::KnowledgeManage,
                Permission::KnowledgePublish,
                Permission::IntegrationsView,
                Permission::AssistantsView,
                Permission::EscalationsView,
                Permission::AlertsView,
            ],

            self::CostAnalyst => [
                Permission::OverviewView,
                Permission::ConversationsView,
                Permission::ConversationsExport,
                Permission::PulseView,
                Permission::UsageView,
                Permission::UsageManageBudgets,
                Permission::ProvidersView,
                Permission::AssistantsView,
                Permission::IntegrationsView,
                Permission::AlertsView,
            ],

            self::SupportAgent => [
                Permission::OverviewView,
                Permission::ConversationsView,
                Permission::ConversationsViewContent,
                Permission::EscalationsView,
                Permission::EscalationsHandle,
                Permission::KnowledgeView,
                Permission::AlertsView,
            ],

            // مراجعة فقط: يرى كل شيء عدا محتوى المحادثات الخام، ولا يغيّر شيئًا.
            self::SecurityAuditor => [
                Permission::OverviewView,
                Permission::ProjectView,
                Permission::ConversationsView,
                Permission::PulseView,
                Permission::UsageView,
                Permission::ProvidersView,
                Permission::AssistantsView,
                Permission::IntegrationsView,
                Permission::KnowledgeView,
                Permission::EscalationsView,
                Permission::AlertsView,
                Permission::AuditView,
                Permission::AuditExport,
                Permission::UsersView,
                Permission::SettingsView,
            ],
        };
    }

    public function grants(Permission $permission): bool
    {
        return in_array($permission, $this->permissions(), strict: true);
    }
}
