<?php

declare(strict_types=1);

namespace App\Domains\Providers\Enums;

/**
 * سويتشات التحكم — وثيقة التصميم §4 (مفاتيح التحكم السريعة) و§8 (سياسات التحويل).
 *
 * Stored per key so a new switch is added without a migration, while the enum
 * keeps the vocabulary closed and typed.
 */
enum ProviderSetting: string
{
    // مفاتيح التحكم السريعة — شاشة النظرة العامة
    case AssistantEnabled = 'assistant_enabled';
    case QualityMonitoring = 'quality_monitoring';
    case MaintenanceMode = 'maintenance_mode';

    // سياسات التحويل التلقائي — شاشة المزودين
    case AutoFailover = 'auto_failover';
    case AutoReturnToPrimary = 'auto_return_to_primary';
    case BlockFailoverForSensitive = 'block_failover_for_sensitive';
    case StopCostlyToolsWhenOutOfCredit = 'stop_costly_tools_when_out_of_credit';

    public function label(): string
    {
        return match ($this) {
            self::AssistantEnabled => 'تشغيل المساعد',
            self::QualityMonitoring => 'مراقبة الجودة',
            self::MaintenanceMode => 'وضع الصيانة',
            self::AutoFailover => 'التحويل التلقائي',
            self::AutoReturnToPrimary => 'العودة للمزود الأساسي',
            self::BlockFailoverForSensitive => 'منع التحويل للحالات الحساسة',
            self::StopCostlyToolsWhenOutOfCredit => 'إيقاف الأدوات المكلفة عند نفاد الرصيد',
        };
    }

    public function defaultEnabled(): bool
    {
        // وضع الصيانة ومنع التحويل للحساسة يبدآن مطفأين — تفعيلهما قرار واعٍ.
        return match ($this) {
            self::MaintenanceMode, self::BlockFailoverForSensitive => false,
            default => true,
        };
    }

    /**
     * @return list<self>
     */
    public static function quickControls(): array
    {
        return [
            self::AssistantEnabled,
            self::AutoFailover,
            self::AutoReturnToPrimary,
            self::StopCostlyToolsWhenOutOfCredit,
            self::QualityMonitoring,
            self::MaintenanceMode,
        ];
    }

    /**
     * @return list<self>
     */
    public static function failoverPolicies(): array
    {
        return [
            self::AutoFailover,
            self::AutoReturnToPrimary,
            self::BlockFailoverForSensitive,
            self::StopCostlyToolsWhenOutOfCredit,
        ];
    }
}
