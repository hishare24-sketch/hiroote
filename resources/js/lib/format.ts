/**
 * تنسيق موحّد للأرقام والمُدد والتواريخ.
 *
 * الأرقام بالخانات اللاتينية (`nu-latn`) عمدًا: الجداول المالية تُقرأ وتُقارن
 * أسرع بها، والمصطلحات كلها عربية.
 *
 * التقويم ميلادي (`ca-gregory`) صراحةً لا هجريًا: `ar-SA` يفترض أم القرى، وكل
 * ما تقيسه هذه اللوحة — دورة الفوترة، سقف الميزانية الشهري، عقود المزودين —
 * ميلادي. تاريخ هجري بجانب فاتورة ميلادية يخلق تعارضًا لا يحلّه المشغّل.
 */

const LOCALE = 'ar-u-ca-gregory-nu-latn';

export function formatNumber(value: number, fractionDigits = 0): string {
    return new Intl.NumberFormat(LOCALE, {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    }).format(value);
}

/** يختصر الأرقام الكبيرة حتى لا تكسر عمود الجدول: ١٢٤٠٠٠ → 124 ألف. */
export function formatCompact(value: number): string {
    if (Math.abs(value) >= 1_000_000) {
        return `${formatNumber(value / 1_000_000, 1)} مليون`;
    }

    if (Math.abs(value) >= 1_000) {
        return `${formatNumber(value / 1_000, 1)} ألف`;
    }

    return formatNumber(value);
}

export function formatMoney(value: number, currency = 'SAR', fractionDigits = 2): string {
    const symbol = currency === 'SAR' ? 'ر.س' : currency;

    return `${formatNumber(value, fractionDigits)} ${symbol}`;
}

export function formatPercent(value: number, fractionDigits = 1): string {
    return `${formatNumber(value, Number.isInteger(value) ? 0 : fractionDigits)}%`;
}

/** المدة بصيغة قصيرة قابلة للمسح البصري في عمود ضيق. */
export function formatDuration(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    if (seconds < 60) {
        return `${formatNumber(seconds)} ثانية`;
    }

    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    if (minutes < 60) {
        return rest === 0
            ? `${formatNumber(minutes)} دقيقة`
            : `${formatNumber(minutes)}:${String(rest).padStart(2, '0')} دقيقة`;
    }

    const hours = Math.floor(minutes / 60);

    return `${formatNumber(hours)} ساعة ${formatNumber(minutes % 60)} دقيقة`;
}

export function formatLatency(milliseconds: number | null): string {
    if (milliseconds === null) {
        return '—';
    }

    return milliseconds < 1000
        ? `${formatNumber(milliseconds)} م.ث`
        : `${formatNumber(milliseconds / 1000, 1)} ثانية`;
}

export function formatDateTime(iso: string): string {
    return new Intl.DateTimeFormat(LOCALE, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export function formatDate(iso: string): string {
    return new Intl.DateTimeFormat(LOCALE, { dateStyle: 'medium' }).format(new Date(iso));
}

export function formatDayMonth(iso: string): string {
    return new Intl.DateTimeFormat(LOCALE, { day: 'numeric', month: 'short' }).format(
        new Date(iso),
    );
}

/** «منذ ٣ ساعات» — للأعمدة التي يهمّ فيها القرب لا التاريخ الدقيق. */
export function formatRelative(iso: string): string {
    const diffSeconds = Math.round((Date.now() - new Date(iso).getTime()) / 1000);
    const formatter = new Intl.RelativeTimeFormat(LOCALE, { numeric: 'auto' });

    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['second', 60],
        ['minute', 60],
        ['hour', 24],
        ['day', 30],
        ['month', 12],
    ];

    let value = diffSeconds;

    for (const [unit, step] of units) {
        if (Math.abs(value) < step) {
            return formatter.format(-value, unit);
        }

        value = Math.round(value / step);
    }

    return formatter.format(-value, 'year');
}

/** يعرض التغيّر مع إشارته؛ null يعني لا مقارنة ممكنة (الفترة السابقة صفر). */
export function formatChange(value: number | null): string {
    if (value === null) {
        return '—';
    }

    const sign = value > 0 ? '+' : '';

    return `${sign}${formatNumber(value, Number.isInteger(value) ? 0 : 1)}%`;
}
