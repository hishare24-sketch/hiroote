/**
 * Shared contracts between Laravel and the SPA (وثيقة 03 §4 —
 * كل API contract له Type أو Schema مشترك).
 */

/** Mirrors App\Domains\Administration\Enums\Role. */
export type Role =
    | 'system_admin'
    | 'ai_manager'
    | 'knowledge_manager'
    | 'cost_analyst'
    | 'support_agent'
    | 'security_auditor';

/** Mirrors App\Domains\Administration\Enums\Permission. */
export type Permission =
    | 'overview.view'
    | 'conversations.view'
    | 'conversations.view_content'
    | 'conversations.export'
    | 'usage.view'
    | 'usage.manage_budgets'
    | 'providers.view'
    | 'providers.manage'
    | 'providers.manage_credentials'
    | 'providers.failover'
    | 'assistants.view'
    | 'assistants.manage'
    | 'integrations.view'
    | 'integrations.manage'
    | 'knowledge.view'
    | 'knowledge.manage'
    | 'knowledge.publish'
    | 'escalations.view'
    | 'escalations.handle'
    | 'alerts.view'
    | 'alerts.manage'
    | 'audit.view'
    | 'audit.export'
    | 'users.view'
    | 'users.manage'
    | 'settings.view'
    | 'settings.manage'
    | 'maintenance.toggle';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: Role;
    role_label: string;
}

export interface SharedProps {
    auth: {
        user: AuthUser | null;
        permissions: Permission[];
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    requestId: string;
    [key: string]: unknown;
}

/** The five status colours fixed by وثيقة 03 §5. */
export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

/** نغمة تشمل لون الهوية إضافةً إلى الحالات الخمس — للشارات والبطاقات. */
export type Tone = StatusTone | 'accent';

/** The nine states every component must cover (وثيقة التصميم §18). */
export type ViewState =
    | 'normal'
    | 'loading'
    | 'empty'
    | 'error'
    | 'warning'
    | 'success'
    | 'unauthorized'
    | 'offline'
    | 'provider_unavailable';

/** The standard API error envelope (وثيقة 03 §7). */
export interface ApiError {
    error: {
        code: string;
        message: string;
        details: Record<string, unknown>;
        request_id: string;
    };
}
