<?php

declare(strict_types=1);

namespace Tests;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\CurrentProject;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * يفعّل مشروعًا خارج دورة الطلب.
     *
     * الطلبات عبر HTTP يحلّ لها `ResolveCurrentProject` المشروع تلقائيًا؛ أما
     * الاختبارات التي تستدعي البوابة مباشرةً فلا middleware لها، والصلاحية
     * سؤال عن «ماذا يملك هذا الشخص هنا» فتحتاج «هنا» (ADR-0003 §3).
     */
    protected function withProject(?Project $project = null): Project
    {
        $project ??= ProjectFactory::default();

        app(CurrentProject::class)->set($project);

        return $project;
    }
}
