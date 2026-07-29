<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Administration\Models\User;
use App\Domains\Projects\Services\CurrentProject;
use App\Support\Help\HelpRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * شرح الشاشة الحالية.
 *
 * يُجلب عند فتح أيقونة الشرح لا مع كل تنقّل: نصوص اثنتي عشرة شاشة في حمولة كل
 * صفحة كلفةٌ دائمة لفائدة نادرة، والجلب عند الطلب يجعلها صفرًا حتى تُطلب.
 */
class HelpController extends Controller
{
    public function __construct(
        private readonly HelpRegistry $registry,
        private readonly CurrentProject $current,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $screen = (string) $request->query('screen', '');
        $topic = $this->registry->find($screen);

        if ($topic === null) {
            return response()->json([
                'error' => [
                    'code' => 'help_topic_missing',
                    'message' => 'لا شرح مسجَّل لهذه الشاشة.',
                    'details' => ['screen' => $screen],
                ],
            ], 404);
        }

        $user = $request->user();
        $project = $this->current->get();

        // الدور يُحلّ لكل مشروع (ADR-0003)، فالملاحظة الموجَّهة تتبع دور القارئ
        // في المشروع الذي يقف فيه لا دورًا عامًّا.
        $role = $user instanceof User && $project !== null
            ? $user->roleIn($project)
            : null;

        return response()->json($topic->payload($role));
    }
}
