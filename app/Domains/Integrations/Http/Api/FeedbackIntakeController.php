<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Http\Api;

use App\Domains\Knowledge\Actions\RecordKnowledgeGap;
use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Knowledge\Enums\FeedbackSource;
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
    public function __construct(private readonly RecordKnowledgeGap $gaps) {}

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

        // قاعدة التكرار واحدة لكل من يرفع ثغرة — الجسر والـ Orchestrator معًا.
        $result = $this->gaps->handle(
            project: $project,
            body: $validated['body'],
            screen: $screen,
            kind: FeedbackKind::from($validated['kind'] ?? FeedbackKind::Unanswered->value),
            // المصدر «مساعد» لا «دعم»: ما يأتي من الجسر إشارةٌ تحتاج تحققًا
            // ميدانيًا قبل أن يُبنى عليها تعديل.
            source: FeedbackSource::Assistant,
        );

        return response()->json([
            'id' => $result['feedback']->id,
            'occurrences' => $result['feedback']->occurrences,
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }
}
