/**
 * عقود شاشة الاستهلاك والتكلفة — وثيقة 06 §7.
 *
 * القيم المالية أرقام بالريال (SAR افتراضًا من `config/hiroote.php`)، والنسب
 * كلها 0–100 — لا تُقسَم مرة ثانية في الواجهة.
 */

import type { Tone } from '@/types';

/** البطاقات الرئيسية التسع — وثيقة 06 §7. */
export interface UsageTotals {
    total_tokens: number;
    input_tokens: number;
    output_tokens: number;
    knowledge_tokens: number;
    attachment_tokens: number;
    tool_tokens: number;
    total_cost: number;
    remaining_balance: number;
    projected_month_cost: number;
    currency: string;
}

/** نقطة على منحنى «الاستهلاك عبر الزمن». */
export interface UsagePoint {
    date: string;
    tokens: number;
    cost: number;
}

/** مقارنة الفترة الحالية بالسابقة — وثيقة 06 §7. */
export interface PeriodComparison {
    current_tokens: number;
    previous_tokens: number;
    current_cost: number;
    previous_cost: number;
    /** التغير بالنسبة المئوية، موجب أو سالب؛ null حين كانت الفترة السابقة صفرًا. */
    tokens_change: number | null;
    cost_change: number | null;
}

/** شريحة في توزيع التكلفة أو التوكن (حسب المزود أو القسم أو النموذج). */
export interface UsageSlice {
    label: string;
    tokens: number;
    cost: number;
    /** حصة الشريحة من المجموع، 0–100. */
    share: number;
    tone: Tone;
}

/** المتوسطات الثلاثة — وثيقة 06 §7. */
export interface UsageAverages {
    cost_per_conversation: number;
    cost_per_response: number;
    cost_per_user: number;
}

/** صف في «أكثر العمليات تكلفة». */
export interface CostlyOperation {
    label: string;
    section: string | null;
    count: number;
    total_cost: number;
    avg_cost: number;
}

/** تنبيه الانحراف عن الميزانية — وثيقة 06 §7. */
export interface BudgetStatus {
    monthly_limit: number;
    spent: number;
    /** المصروف كنسبة من السقف، 0–100 (قد يتجاوز 100 عند التخطي). */
    consumed_percent: number;
    warn_at_percent: number;
    critical_at_percent: number;
    hard_stop: boolean;
    tone: Tone;
    message: string;
    currency: string;
}

export interface TokenBreakdownItem {
    key: 'input' | 'output' | 'knowledge' | 'attachment' | 'tool';
    label: string;
    tokens: number;
    /** حصة النوع من إجمالي التوكن، 0–100. */
    share: number;
    tone: Tone;
}
