<?php

declare(strict_types=1);

namespace App\Domains\Administration\Enums;

/**
 * شارة نوع الحدث في سجل التدقيق — وثيقة التصميم §17.
 *
 * مشتقة من بادئة الـ action فلا تحتاج عمودًا إضافيًا ولا تُنسى عند إضافة
 * إجراء جديد؛ ما لا يطابق شيئًا يقع على «تشغيل».
 */
enum AuditCategory: string
{
    case Settings = 'settings';
    case Alert = 'alert';
    case Failover = 'failover';
    case Knowledge = 'knowledge';
    case Error = 'error';
    case Operation = 'operation';
    case HealthCheck = 'health_check';
    case Auth = 'auth';

    public function label(): string
    {
        return match ($this) {
            self::Settings => 'إعدادات',
            self::Alert => 'تنبيه',
            self::Failover => 'تحويل تلقائي',
            self::Knowledge => 'معرفة',
            self::Error => 'خطأ',
            self::Operation => 'تشغيل',
            self::HealthCheck => 'فحص',
            self::Auth => 'دخول',
        };
    }

    /** يطابق نغمات الحالة الخمس في نظام التصميم. */
    public function tone(): string
    {
        return match ($this) {
            self::Settings => 'accent',
            self::Alert => 'warning',
            self::Failover => 'info',
            self::Knowledge => 'info',
            self::Error => 'danger',
            self::HealthCheck => 'success',
            self::Operation, self::Auth => 'neutral',
        };
    }

    public static function fromAction(string $action): self
    {
        return match (true) {
            str_starts_with($action, 'settings.') => self::Settings,
            str_starts_with($action, 'alerts.') => self::Alert,
            str_contains($action, 'failover') => self::Failover,
            str_starts_with($action, 'knowledge.') => self::Knowledge,
            str_contains($action, 'failed'), str_contains($action, 'error') => self::Error,
            str_contains($action, 'health'), str_contains($action, 'check') => self::HealthCheck,
            str_starts_with($action, 'auth.') => self::Auth,
            default => self::Operation,
        };
    }
}
