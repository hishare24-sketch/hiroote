<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Alerts\Actions\EvaluateAlertRules;
use App\Domains\Alerts\Enums\AlertAction;
use App\Domains\Alerts\Enums\AlertChannel;
use App\Domains\Alerts\Enums\AlertComparison;
use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Enums\AlertSeverity;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Seeder;

/**
 * قواعد تنبيه تجريبية — **لا تُشغَّل في الإنتاج**.
 *
 * الأحداث هنا لا تُزرع: القواعد تُنشأ ثم يُشغَّل المُقيِّم على بيانات المشروع
 * الفعلية، فما يظهر في السجل هو ما تجاوز حدَّه حقًّا. حدثٌ مزروع يجعل الشاشة
 * تبدو عاملة وهي لم تقِس شيئًا.
 */
class AlertsSeeder extends Seeder
{
    /** @var list<array{name: string, about: string, metric: AlertMetric, comparison: AlertComparison, threshold: float, window: int, severity: AlertSeverity, cooldown: int, action: AlertAction}> */
    private const RULES = [
        [
            'name' => 'ارتفاع التحويل إلى بشري',
            'about' => 'المساعد يعجز عن الإجابة أكثر من المعتاد.',
            'metric' => AlertMetric::EscalationRate,
            'comparison' => AlertComparison::GreaterThan,
            'threshold' => 18.0,
            'window' => 1440,
            'severity' => AlertSeverity::Warning,
            'cooldown' => 240,
            'action' => AlertAction::NotifyOnly,
        ],
        [
            'name' => 'انقطاع المحادثات',
            'about' => 'المستخدمون يغادرون قبل الحصول على إجابة.',
            'metric' => AlertMetric::AbandonRate,
            'comparison' => AlertComparison::GreaterThan,
            'threshold' => 12.0,
            'window' => 1440,
            'severity' => AlertSeverity::Warning,
            'cooldown' => 240,
            'action' => AlertAction::NotifyOnly,
        ],
        [
            'name' => 'بطء الرد',
            'about' => 'متوسط زمن الرد يتجاوز أربع ثوانٍ.',
            'metric' => AlertMetric::AvgResponseMs,
            'comparison' => AlertComparison::GreaterThan,
            'threshold' => 4000.0,
            'window' => 720,
            'severity' => AlertSeverity::Critical,
            'cooldown' => 60,
            'action' => AlertAction::FailoverProvider,
        ],
        [
            'name' => 'هبوط التقييم',
            'about' => 'رضا المستخدمين يقل عن ثلاث ونصف من خمسة.',
            'metric' => AlertMetric::AvgRating,
            'comparison' => AlertComparison::LessThan,
            'threshold' => 3.5,
            'window' => 10080,
            'severity' => AlertSeverity::Warning,
            'cooldown' => 1440,
            'action' => AlertAction::NotifyOnly,
        ],
        [
            'name' => 'تجاوز تكلفة اليوم',
            'about' => 'مجموع تكلفة اليوم يتجاوز السقف المتفق عليه.',
            'metric' => AlertMetric::CostTotal,
            'comparison' => AlertComparison::GreaterThan,
            'threshold' => 400.0,
            'window' => 1440,
            'severity' => AlertSeverity::Critical,
            'cooldown' => 720,
            'action' => AlertAction::NotifyOnly,
        ],
        [
            'name' => 'نفاد رصيد مزود',
            'about' => 'أدنى رصيد بين المزودين يقترب من الصفر.',
            'metric' => AlertMetric::ProviderBalance,
            'comparison' => AlertComparison::LessThan,
            'threshold' => 150.0,
            'window' => 0,
            'severity' => AlertSeverity::Critical,
            'cooldown' => 360,
            'action' => AlertAction::FailoverProvider,
        ],
        [
            'name' => 'تراكم ملاحظات المعرفة',
            'about' => 'أسئلة بلا إجابة تنتظر معالجة.',
            'metric' => AlertMetric::OpenKnowledgeNotes,
            'comparison' => AlertComparison::GreaterOrEqual,
            'threshold' => 5.0,
            'window' => 0,
            'severity' => AlertSeverity::Info,
            'cooldown' => 2880,
            'action' => AlertAction::NotifyOnly,
        ],
        [
            'name' => 'ثقة منخفضة',
            'about' => 'ربع المحادثات أو أكثر بثقة دون ٦٠٪.',
            'metric' => AlertMetric::LowConfidenceRate,
            'comparison' => AlertComparison::GreaterThan,
            'threshold' => 25.0,
            'window' => 4320,
            'severity' => AlertSeverity::Warning,
            'cooldown' => 720,
            'action' => AlertAction::NotifyOnly,
        ],
    ];

    public function __construct(private readonly EvaluateAlertRules $evaluate) {}

    public function run(): void
    {
        $watchers = User::query()
            ->whereIn('role', [Role::SystemAdmin->value, Role::AiManager->value])
            ->get();

        foreach (Project::query()->get() as $project) {
            foreach (self::RULES as $definition) {
                $rule = AlertRule::query()->updateOrCreate(
                    ['project_id' => $project->id, 'name' => $definition['name']],
                    [
                        'description' => $definition['about'],
                        'metric' => $definition['metric'],
                        'comparison' => $definition['comparison'],
                        'threshold' => $definition['threshold'],
                        'window_minutes' => $definition['window'],
                        'severity' => $definition['severity'],
                        'cooldown_minutes' => $definition['cooldown'],
                        'auto_action' => $definition['action'],
                        'is_enabled' => true,
                        'section_ids' => [],
                        'provider_ids' => [],
                    ],
                );

                if ($rule->recipients()->exists()) {
                    continue;
                }

                foreach ($watchers as $watcher) {
                    $rule->recipients()->create([
                        'user_id' => $watcher->id,
                        'channel' => AlertChannel::InApp,
                    ]);
                }

                // قناة بريد واحدة لإظهار «معلّق» في سجل الإرسال بصدق.
                if ($definition['severity'] === AlertSeverity::Critical) {
                    $rule->recipients()->create([
                        'email' => 'ops@hiroote.test',
                        'channel' => AlertChannel::Email,
                    ]);
                }
            }

            $this->evaluate->handle($project);
        }
    }
}
