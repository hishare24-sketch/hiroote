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
     * اختبارات الخادم لا تنتظر حزمًا مبنيّة.
     *
     * `@vite` في القالب يرمي إن لم يوجد `public/build/manifest.json`، فكل
     * اختبار يعرض صفحة كان يردّ 500 على أي جهاز لم يُبنَ فيه الواجهة —
     * ووظيفة CI للـ PHP لا تبني شيئًا. والبناء تحرسه وظيفة الواجهة وحدها،
     * فاشتراطه هنا يقيس تشغيل `npm run build` لا سلوك الخادم.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

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
