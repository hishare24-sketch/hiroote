/**
 * عقود شاشتَي سلوك المساعد وتكامل الأقسام — وثيقة 06 §12 و§13 و§14.
 *
 * كل ما هنا يخص المشروع النشط وحده (ADR-0003): لا حقل يعبر بين مشروعين.
 */

import type { Tone } from '@/types';
import type { AssistantLevel, EnumRef } from '@/types/conversations';

/** بطاقة مستوى قابلة للتحرير — وثيقة 06 §12. */
export interface AssistantLevelCard {
    id: number;
    key: EnumRef<AssistantLevel>;
    label: string;
    description: string;
    response_length: string;
    token_limit: number;
    /** المقاييس أدناه 1–5 عدا الإبداع فهو 0–100. */
    intelligence: number;
    initiative: number;
    creativity: number;
    detail: number;
    formality: number;
    reads_attachments: boolean;
    calls_data: boolean;
    executes_actions: boolean;
    /** تحت هذه العتبة يُحوَّل بدل أن يخمّن، 0–100. */
    confidence_threshold: number;
    model_id: number | null;
    model: string | null;
    expected_cost: number;
    is_available: boolean;
    is_default: boolean;
}

/** إعداد تحكم المستخدم بالمستوى — وثيقة 06 §12. */
export interface AssistantProfile {
    default_level: AssistantLevel;
    allow_level_change: boolean;
    level_scope: 'persistent' | 'conversation';
    availability: 'all' | 'membership' | 'role';
    availability_key: string | null;
}

/** سويتش وظيفة — وثيقة 06 §13. */
export interface AssistantFunctionToggle {
    key: string;
    label: string;
    description: string;
    enabled: boolean;
    sensitive: boolean;
    /** تعريفها مُعتمد وتنفيذها لم يصل — تظهر معلّمة وموقوفة. */
    awaits_implementation: boolean;
    depends_on: string | null;
    depends_on_label: string | null;
    tone: Tone;
}

/** عمود في مصفوفة التكامل — وثيقة 06 §14. */
export interface CapabilityColumn {
    key: string;
    label: string;
    short_label: string;
    description: string;
    sensitive: boolean;
    depends_on: string | null;
    depends_on_label: string | null;
}

/** صف في المصفوفة: قسم واحد بقدراته. */
export interface SectionRow {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    sort_order: number;
    capabilities: Record<string, boolean>;
    level: EnumRef<AssistantLevel> | null;
    model_id: number | null;
    model: string | null;
    /** آخر 30 يومًا — تُقرأ من المحادثات لا من عدّاد محفوظ. */
    conversations: number;
    resolution_rate: number | null;
    escalation_rate: number | null;
    updated_at: string;
}

export interface SelectOptionPayload {
    value: string;
    label: string;
}
