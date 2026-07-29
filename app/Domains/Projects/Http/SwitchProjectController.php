<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\CurrentProject;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * تبديل المشروع النشط — ADR-0003 §4.
 */
class SwitchProjectController extends Controller
{
    public function __construct(private readonly CurrentProject $current) {}

    public function __invoke(Request $request, Project $project): RedirectResponse
    {
        $user = $request->user();

        // العضوية شرط التبديل: تمرير معرّف مشروع لا ينتمي إليه المستخدم محاولةُ
        // وصول لا خطأ إدخال، فتُعامَل كغير موجود لا كـ 403 يؤكد وجوده.
        abort_unless(
            $user !== null && $this->current->availableTo($user)->contains('id', $project->id),
            404,
        );

        $request->session()->put(CurrentProject::SESSION_KEY, $project->id);

        // العودة إلى الشاشة نفسها في المشروع الجديد، بلا فلاتر الفترة السابقة:
        // «آخر 30 يومًا» في مشروع لا تعني الشيء نفسه في آخر.
        return back(fallback: '/');
    }
}
