<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Http\Api;

use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Knowledge\Enums\FeedbackSource;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateProjectApiKey;
use App\Support\Http\RequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * استقبال رصد من المشروع الخارجي — وثيقة 06 §15.
 *
 * ما يصل هنا **رصدٌ لا حكم**: يدخل الطابور البشري ولا يعدّل شيئًا. الرصد
 * المتكرر بالنص نفسه على الشاشة نفسها يزيد عدّاده بدل أن يُنشئ صفًّا جديدًا —
 * سبعُ نسخ من سؤال واحد تُخفي أنه سؤال واحد سأله سبعة.
 */
class FeedbackIntakeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $project = $request->attributes->get(AuthenticateProjectApiKey::PROJECT);

        if (! $project instanceof Project) {
            abort(401);
        }

        $validated = $request->validate([
            'screen' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:3', 'max:1000'],
            'kind' => ['nullable', 'string', 'in:feedback,unanswered,suggestion'],
        ]);

        $screen = KnowledgeScreen::query()
            ->forProject($project)
            ->where('key', $validated['screen'])
            ->first();

        if ($screen === null) {
            return response()->json([
                'error' => [
                    'code' => 'screen_not_found',
                    'message' => 'لا شاشة بهذا المفتاح في هذا المشروع.',
                    'details' => ['screen' => $validated['screen']],
                    'request_id' => RequestId::current(),
                ],
            ], 404);
        }

        $kind = FeedbackKind::from($validated['kind'] ?? FeedbackKind::Unanswered->value);

        $existing = KnowledgeFeedback::query()
            ->forProject($project)
            ->where('screen_id', $screen->id)
            ->where('body', $validated['body'])
            ->open()
            ->first();

        if ($existing !== null) {
            // العدّاد صغير (tinyint) فيُحدّ عند سقفه: رقمٌ يلتف إلى الصفر يقلب
            // «تكرر كثيرًا» إلى «لم يتكرر».
            $existing->forceFill([
                'occurrences' => min($existing->occurrences + 1, 255),
            ])->save();

            return response()->json([
                'id' => $existing->id,
                'occurrences' => $existing->occurrences,
                'created' => false,
            ]);
        }

        $feedback = KnowledgeFeedback::query()->create([
            'project_id' => $project->id,
            'section_id' => $screen->section_id,
            'screen_id' => $screen->id,
            'kind' => $kind,
            // المصدر «مساعد» لا «دعم»: ما يأتي من الجسر إشارةٌ تحتاج تحققًا
            // ميدانيًا قبل أن يُبنى عليها تعديل.
            'source' => FeedbackSource::Assistant,
            'body' => $validated['body'],
            'occurrences' => 1,
        ]);

        return response()->json([
            'id' => $feedback->id,
            'occurrences' => 1,
            'created' => true,
        ], 201);
    }
}
