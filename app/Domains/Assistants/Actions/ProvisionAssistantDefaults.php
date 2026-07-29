<?php

declare(strict_types=1);

namespace App\Domains\Assistants\Actions;

use App\Domains\Assistants\Enums\AssistantFunction;
use App\Domains\Assistants\Models\AssistantFunctionSetting;
use App\Domains\Assistants\Models\AssistantLevelSetting;
use App\Domains\Assistants\Models\AssistantProfile;
use App\Domains\Conversations\Enums\AssistantLevel;
use App\Domains\Projects\Models\Project;

/**
 * يجهّز سلوك المساعد لمشروع جديد — وثيقة 06 §12 و§13.
 *
 * يُستدعى عند إنشاء المشروع لا عند فتح الشاشة: مشروعٌ بلا مستويات يعرض شاشة
 * فارغة تبدو معطوبة، والمستويات الأربعة نقطة انطلاق تُحرَّر لا قالبٌ ثابت.
 *
 * `firstOrCreate` عمدًا: إعادة التشغيل لا تدهس تخصيصًا حرّره المشغّل.
 */
final readonly class ProvisionAssistantDefaults
{
    /**
     * القيم الافتتاحية للمستويات الأربعة.
     *
     * تتدرّج معًا لا عشوائيًا: كلما ارتفع المستوى ارتفعت المبادرة والتفصيل وحد
     * التوكن، وانخفضت عتبة الثقة التي يحوّل تحتها — الخبير يحاول قبل أن يحوّل،
     * والمباشر يحوّل مبكرًا بدل أن يطيل.
     *
     * @var array<string, array<string, mixed>>
     */
    private const LEVELS = [
        'direct' => [
            'label' => 'مباشر وموجز',
            'description' => 'جواب واحد قصير بلا مقدمات ولا اقتراحات. يناسب من يعرف ما يريد.',
            'response_length' => 'جملة إلى ثلاث',
            'token_limit' => 600,
            'intelligence' => 2,
            'initiative' => 1,
            'creativity' => 10,
            'detail' => 1,
            'formality' => 3,
            'reads_attachments' => false,
            'calls_data' => true,
            'executes_actions' => false,
            'confidence_threshold' => 80,
            'expected_cost' => '0.0900',
            'sort_order' => 1,
        ],
        'balanced' => [
            'label' => 'متوازن',
            'description' => 'جواب كافٍ مع خطوة تالية واحدة عند الحاجة. الافتراضي لأغلب الأقسام.',
            'response_length' => 'فقرة قصيرة',
            'token_limit' => 1200,
            'intelligence' => 3,
            'initiative' => 3,
            'creativity' => 30,
            'detail' => 3,
            'formality' => 3,
            'reads_attachments' => true,
            'calls_data' => true,
            'executes_actions' => false,
            'confidence_threshold' => 70,
            'expected_cost' => '0.1800',
            'sort_order' => 2,
        ],
        'proactive' => [
            'label' => 'مستشار استباقي',
            'description' => 'يجيب ثم يقترح ما لم يُسأل عنه مما يخدم الطلب نفسه.',
            'response_length' => 'فقرة مع نقاط',
            'token_limit' => 2000,
            'intelligence' => 4,
            'initiative' => 5,
            'creativity' => 55,
            'detail' => 4,
            'formality' => 2,
            'reads_attachments' => true,
            'calls_data' => true,
            'executes_actions' => false,
            'confidence_threshold' => 65,
            'expected_cost' => '0.3100',
            'sort_order' => 3,
        ],
        'expert' => [
            'label' => 'خبير متعمق',
            'description' => 'تحليل كامل بالأسباب والبدائل. يناسب الحالات المعقدة لا الأسئلة اليومية.',
            'response_length' => 'شرح مفصّل',
            'token_limit' => 3600,
            'intelligence' => 5,
            'initiative' => 4,
            'creativity' => 45,
            'detail' => 5,
            'formality' => 4,
            'reads_attachments' => true,
            'calls_data' => true,
            'executes_actions' => false,
            'confidence_threshold' => 55,
            'expected_cost' => '0.5400',
            'sort_order' => 4,
        ],
    ];

    public function handle(Project $project): void
    {
        AssistantProfile::forProject($project);

        foreach (self::LEVELS as $key => $attributes) {
            AssistantLevelSetting::query()->firstOrCreate(
                ['project_id' => $project->id, 'key' => $key],
                $attributes,
            );
        }

        foreach (AssistantFunction::cases() as $function) {
            AssistantFunctionSetting::query()->firstOrCreate(
                ['project_id' => $project->id, 'key' => $function->value],
                ['is_enabled' => $function->defaultEnabled()],
            );
        }
    }

    /** @return list<AssistantLevel> */
    public static function levelOrder(): array
    {
        return [
            AssistantLevel::Direct,
            AssistantLevel::Balanced,
            AssistantLevel::Proactive,
            AssistantLevel::Expert,
        ];
    }
}
