/**
 * عقود شاشات الأداء والمحادثات والتحويل — وثيقة 06 §6 و§10.
 *
 * الأسماء هنا مطابقة لأسماء الأعمدة في `app/Domains/Conversations` حرفيًا:
 * حين يصل الـ Orchestrator يتغيّر مصدر البيانات لا شكلها، فلا تُمَسّ الشاشات.
 */

import type { Tone } from '@/types';

export type { Tone };

/** Mirrors App\Domains\Conversations\Enums\ConversationOutcome. */
export type ConversationOutcome = 'resolved' | 'ticket' | 'human' | 'abandoned' | 'open';

/** Mirrors App\Domains\Conversations\Enums\AssistantLevel. */
export type AssistantLevel = 'direct' | 'balanced' | 'proactive' | 'expert';

/** Mirrors App\Domains\Conversations\Enums\MessageRole. */
export type MessageRole = 'user' | 'assistant' | 'system';

/** Mirrors App\Domains\Conversations\Enums\ToolOutcome. */
export type ToolOutcome = 'succeeded' | 'failed' | 'skipped';

/** Mirrors App\Domains\Conversations\Enums\EscalationTarget. */
export type EscalationTarget = 'specialist_assistant' | 'human_agent' | 'ticket';

/** Mirrors App\Domains\Conversations\Enums\EscalationSeverity. */
export type EscalationSeverity = 'critical' | 'high' | 'medium' | 'low';

/**
 * قيمة enum مُرسَلة كاملة من الخادم: القيمة للمنطق، التسمية للعرض، النغمة للون.
 * الواجهة لا تترجم ولا تختار لونًا — مصدر الحقيقة واحد في PHP (وثيقة 03 §5).
 */
export interface EnumRef<T extends string> {
    value: T;
    label: string;
    tone: Tone;
}

/** صف واحد في جدول المحادثات — أعمدة وثيقة 06 §6. */
export interface ConversationRow {
    id: number;
    reference: string;
    user_label: string | null;
    section: string;
    assistant: string | null;
    level: EnumRef<AssistantLevel>;
    provider: string | null;
    model: string | null;
    duration_seconds: number;
    message_count: number;
    total_tokens: number;
    cost: number;
    outcome: EnumRef<ConversationOutcome>;
    escalation: EnumRef<EscalationTarget> | null;
    rating: number | null;
    started_at: string;
}

/** مؤشرات الشاشة العليا — وثيقة 06 §6. */
export interface ConversationMetrics {
    conversations: number;
    messages: number;
    unique_users: number;
    avg_duration_seconds: number;
    avg_first_response_ms: number;
    avg_response_ms: number;
    /** النسب أدناه 0–100. */
    first_answer_resolution_rate: number;
    unattended_resolution_rate: number;
    misunderstanding_rate: number;
    rephrase_rate: number;
    abandonment_rate: number;
    /** 1.0–5.0، أو null حين لا يوجد تقييم واحد في الفترة. */
    avg_rating: number | null;
    rated_count: number;
}

/** صف في «أكثر الأسئلة» أو «أكثر الأقسام» أو «نقاط التعثر». */
export interface RankedItem {
    label: string;
    caption: string | null;
    count: number;
    /** حصة البند من المجموع، 0–100. */
    share: number;
    tone: Tone;
}

export interface ConversationTimelineEntry {
    id: number;
    type: string;
    label: string;
    detail: string | null;
    created_at: string;
}

export interface ConversationMessage {
    id: number;
    role: EnumRef<MessageRole>;
    /** null حين لا يملك المستخدم `conversations.view_content` — النص محجوب لا مفقود. */
    content: string | null;
    tokens: number;
    latency_ms: number | null;
    created_at: string;
}

export interface ConversationToolCall {
    id: number;
    name: string;
    outcome: EnumRef<ToolOutcome>;
    duration_ms: number | null;
    error_message: string | null;
    created_at: string;
}

export interface ConversationClick {
    id: number;
    screen: string;
    path: string | null;
    led_to_resolution: boolean;
    created_at: string;
}

/** شاشة تفاصيل المحادثة — وثيقة 06 §6. */
export interface ConversationDetail extends ConversationRow {
    external_user_id: string | null;
    detected_intent: string | null;
    confidence: number | null;
    resolved_first_answer: boolean;
    understood_intent: boolean;
    rephrased: boolean;
    first_response_ms: number | null;
    avg_response_ms: number | null;
    ended_at: string | null;
    /** هل يملك المستخدم صلاحية قراءة نص الرسائل. */
    can_view_content: boolean;
    messages: ConversationMessage[];
    timeline: ConversationTimelineEntry[];
    tools: ConversationToolCall[];
    clicks: ConversationClick[];
    escalation_detail: EscalationRow | null;
}

/** حالة تحويل واحدة — وثيقة 06 §10. */
export interface EscalationRow {
    id: number;
    reference: string;
    conversation_reference: string | null;
    conversation_id: number | null;
    target: EnumRef<EscalationTarget>;
    severity: EnumRef<EscalationSeverity>;
    reason: string;
    section: string;
    subject: string;
    wait_seconds: number | null;
    handling_seconds: number | null;
    resolved_at: string | null;
    created_at: string;
}

/** بطاقة مسار تحويل واحد من المسارات الثلاثة المفصولة بصريًا. */
export interface EscalationPathSummary {
    target: EnumRef<EscalationTarget>;
    count: number;
    /** حصة المسار من كل التحويلات، 0–100. */
    share: number;
    avg_wait_seconds: number | null;
    avg_handling_seconds: number | null;
    open_count: number;
}

/** خطوة في مخطط رحلة التحويل. */
export interface EscalationJourneyStep {
    label: string;
    detail: string;
    count: number;
    /** حصة الخطوة من المحادثات الداخلة للرحلة، 0–100. */
    share: number;
}

/** قاعدة تحديد النية والثقة، وقاعدة التصعيد حسب الحساسية. */
export interface EscalationRule {
    condition: string;
    action: string;
    severity: EnumRef<EscalationSeverity> | null;
}

/** الفلاتر العامة — وثيقة 06 §5. */
export type PeriodKey = 'hour' | 'today' | 'week' | 'month' | 'current_month' | 'custom';

export interface ConversationFilters {
    period: PeriodKey;
    from: string | null;
    to: string | null;
    section: string;
    outcome: string;
    provider: string;
    search: string;
}

/** صفحة مقسّمة من Laravel — الشكل الذي يُنتجه `paginate()->through()`. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: { url: string | null; label: string; active: boolean }[];
}
