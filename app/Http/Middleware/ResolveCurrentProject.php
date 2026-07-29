<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\CurrentProject;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يحلّ المشروع النشط من الجلسة قبل أي فحص صلاحية — ADR-0003 §4.
 *
 * يعمل قبل `EnsurePermission` لأن الدور نفسه يعتمد على المشروع: من لا مشروع
 * له لا دور له ولا صلاحية.
 */
final class ResolveCurrentProject
{
    public function __construct(private readonly CurrentProject $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $available = $this->current->availableTo($user);
        $stored = $request->session()->get(CurrentProject::SESSION_KEY);

        $project = $available->first(
            fn (Project $candidate): bool => $candidate->id === $stored,
        );

        // مشروع محذوف أو سُحبت العضوية منه يسقط بهدوء إلى أول متاح، ولا يترك
        // الجلسة تشير إلى شيء لم يعد يملكه المستخدم.
        $project ??= $available->first();

        if ($project !== null && $project->id !== $stored) {
            $request->session()->put(CurrentProject::SESSION_KEY, $project->id);
        }

        $this->current->set($project);

        return $next($request);
    }
}
