/** أنواع شاشة نبض المشروع — تقابل `App\Domains\Analytics\Services\PulseReport`. */

export interface PulseCoverage {
    expected: number;
    received: number;
    missing: number;
    partial: number;
    revised: number;
    has_any: boolean;
}

export interface PulseMetric {
    total: number | null;
    average: number | null;
    peak: number | null;
    low: number | null;
    /** عدد الأيام التي قيس فيها هذا المقياس — لا طول الفترة. */
    measured_days: number;
    change_percent: number | null;
}

export type PulseMetricKey =
    | 'active_users'
    | 'logins'
    | 'sessions'
    | 'peak_concurrent'
    | 'presence_minutes'
    | 'peak_hour_actions'
    | 'storage_megabytes';

export type PulseMetrics = Record<PulseMetricKey, PulseMetric>;

export interface PulseRatio {
    key: string;
    label: string;
    value: number | null;
    unit: string;
    measured_days: number;
    about: string;
}

export interface PulseScreenRow {
    key: string;
    views: number | null;
    clicks: number | null;
    click_rate: number | null;
    days: number;
}

export interface PulseSectionRow {
    name: string;
    actions: number;
    days: number;
}

export interface PulseSnapshot {
    as_of: string;
    packages: { name: string; subscribers: number }[];
    health: Record<string, number>;
}
