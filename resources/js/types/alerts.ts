import type { EnumRef } from './conversations';
import type { StatusTone, Tone } from './index';

/** وحدة قياس المؤشر — تحدد كيف تُنسَّق القيمة والحد معًا. */
export type MetricUnit = 'percent' | 'count' | 'money' | 'milliseconds' | 'rating';

export interface MetricOption {
    value: string;
    label: string;
    hint: string;
    family: string;
    family_label: string;
    unit: MetricUnit;
    unit_label: string;
    ceiling: number | null;
    windowed: boolean;
    supports_sections: boolean;
    suggested_comparison: string;
    suggested_threshold: number;
}

export interface RecipientRow {
    user_id: number | null;
    email: string | null;
    name: string;
    channel: EnumRef<string>;
    wired: boolean;
}

export interface AlertRuleRow {
    id: number;
    name: string;
    description: string | null;
    metric: EnumRef<string>;
    metric_hint: string;
    unit: MetricUnit;
    windowed: boolean;
    supports_sections: boolean;
    comparison: { value: string; label: string };
    threshold: number;
    window_minutes: number;
    cooldown_minutes: number;
    severity: EnumRef<string>;
    auto_action: EnumRef<string> & { awaits: boolean };
    is_enabled: boolean;
    section_ids: number[];
    provider_ids: number[];
    trigger_count: number;
    last_evaluated_at: string | null;
    last_triggered_at: string | null;
    cooling_down: boolean;
    /** `null` يعني تعذّر القياس لا صفرًا. */
    current_value: number | null;
    current_sample: string;
    breached: boolean;
    recipients: RecipientRow[];
}

export interface DeliveryRow {
    channel: EnumRef<string>;
    target: string;
    status: EnumRef<string>;
    note: string | null;
}

export interface AlertEventRow {
    id: number;
    rule_id: number;
    rule_name: string;
    status: EnumRef<string>;
    severity: EnumRef<string>;
    metric: EnumRef<string>;
    unit: MetricUnit;
    comparison: string;
    threshold: number;
    observed_value: number;
    peak_value: number;
    window_minutes: number;
    sample: string | null;
    triggered_at: string;
    resolved_at: string | null;
    acknowledged_by: string | null;
    deliveries: DeliveryRow[];
}

export interface AlertOptions {
    comparisons: { value: string; label: string }[];
    severities: { value: string; label: string; tone: StatusTone }[];
    channels: { value: string; label: string; wired: boolean; pending_reason: string | null }[];
    actions: { value: string; label: string; awaits: boolean }[];
    sections: { id: number; name: string }[];
    providers: { id: number; name: string }[];
    members: { id: number; name: string; email: string }[];
}

export type { Tone };
