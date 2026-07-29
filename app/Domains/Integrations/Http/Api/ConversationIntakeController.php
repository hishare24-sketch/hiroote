<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Http\Api;

use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateProjectApiKey;
use App\Support\Http\RequestId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * تسجيل محادثة جرت في المشروع الخارجي — وثيقة 02 §5.
 *
 * مفتاح الشاشة يُحفظ مع المحادثة لأنه ما يجعل السؤال «هل تحسّنت نسبة الحل في
 * شاشة السحب بعد تعديل وصفها؟» قابلًا للإجابة. بدونه تبقى دورة الرصد بلا
 * خاتمة: نعدّل ولا نعرف أنفع التعديلُ أم لا.
 *
 * والمرجع الوارد من المشروع مفتاح التفرّد: إعادة إرسال الطلب نفسه — عند فشل
 * شبكة مثلًا — تُحدّث الصف ولا تُنشئ توأمًا يضاعف كل إحصاء.
 */
class ConversationIntakeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $project = $request->attributes->get(AuthenticateProjectApiKey::PROJECT);

        if (! $project instanceof Project) {
            abort(401);
        }

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:64'],
            'screen' => ['nullable', 'string', 'max:120'],
            'section' => ['nullable', 'string', 'max:120'],
            'external_user_id' => ['nullable', 'string', 'max:64'],
            'user_label' => ['nullable', 'string', 'max:120'],
            'outcome' => ['required', 'string', 'in:open,resolved,ticket,human,abandoned'],
            'message_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'confidence' => ['nullable', 'integer', 'min:0', 'max:100'],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'started_at' => ['nullable', 'date'],
        ]);

        $screenKey = $validated['screen'] ?? null;
        $screen = null;

        if ($screenKey !== null) {
            $screen = KnowledgeScreen::query()
                ->forProject($project)
                ->where('key', $screenKey)
                ->with('section')
                ->first();

            // المفتاح المجهول يُرفض ولا يُحفظ: مفتاحٌ مكتوبٌ خطأً يُحفظ بصمت
            // يبني إحصاءً لشاشة لا وجود لها، ولا يكتشفه أحد.
            if ($screen === null) {
                return response()->json([
                    'error' => [
                        'code' => 'screen_not_found',
                        'message' => 'لا شاشة بهذا المفتاح في هذا المشروع.',
                        'details' => ['screen' => $screenKey],
                        'request_id' => RequestId::current(),
                    ],
                ], 404);
            }
        }

        $section = $screen?->section->name
            ?? $this->resolveSectionName($project, $validated['section'] ?? null);

        $conversation = Conversation::query()->updateOrCreate(
            ['project_id' => $project->id, 'reference' => $validated['reference']],
            [
                'ulid' => (string) Str::ulid(),
                'screen_key' => $screenKey,
                'section' => $section ?? 'غير محدد',
                'external_user_id' => $validated['external_user_id'] ?? null,
                'user_label' => $validated['user_label'] ?? null,
                'level' => 'balanced',
                'outcome' => ConversationOutcome::from($validated['outcome']),
                'message_count' => $validated['message_count'] ?? 0,
                'duration_seconds' => $validated['duration_seconds'] ?? 0,
                'confidence' => $validated['confidence'] ?? null,
                'rating' => $validated['rating'] ?? null,
                'started_at' => $validated['started_at'] ?? now(),
            ],
        );

        return response()->json([
            'id' => $conversation->id,
            'reference' => $conversation->reference,
            'screen' => $conversation->screen_key,
            'section' => $conversation->section,
        ], $conversation->wasRecentlyCreated ? 201 : 200);
    }

    /** اسم القسم المرسل، إن طابق قسمًا قائمًا في المشروع. */
    private function resolveSectionName(Project $project, ?string $sectionName): ?string
    {
        if ($sectionName === null) {
            return null;
        }

        $match = ProjectSection::query()
            ->forProject($project)
            ->where('name', $sectionName)
            ->value('name');

        return is_string($match) ? $match : null;
    }
}
