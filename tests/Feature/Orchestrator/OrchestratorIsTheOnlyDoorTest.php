<?php

declare(strict_types=1);

namespace Tests\Feature\Orchestrator;

use App\Domains\Orchestrator\Contracts\AiDriver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * القاعدة رقم ٢ في `CLAUDE.md` مفروضةً بالكود لا بالنية.
 *
 * نداءُ مزودٍ يتجاوز الطبقة **يعمل** — ويترك اللوحة تعرض رموزًا وكلفةً أقلّ من
 * الحقيقة، وهو أسوأ من ألّا تعرض شيئًا: رقمٌ ناقص يُقرأ قياسًا فيُبنى عليه
 * قرار ميزانية. ولذلك يُحرَس بفحصٍ يقرأ الشجرة لا بمراجعةٍ تُنسى.
 */
class OrchestratorIsTheOnlyDoorTest extends TestCase
{
    /** نطاقات مزودي الذكاء — أي ذكرٍ لها خارج الطبقة نداءٌ متجاوز. */
    private const PROVIDER_HOSTS = [
        'api.anthropic.com',
        'api.openai.com',
        '/v1/messages',
        '/v1/chat/completions',
    ];

    #[Test]
    public function no_provider_endpoint_is_named_outside_the_orchestrator(): void
    {
        $offenders = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            if (str_starts_with($file, app_path('Domains/Orchestrator'))) {
                continue;
            }

            $contents = (string) file_get_contents($file);

            foreach (self::PROVIDER_HOSTS as $needle) {
                if (str_contains($contents, $needle)) {
                    $offenders[] = str_replace(base_path().'/', '', $file)." ← {$needle}";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "نداء مزود خارج طبقة الـ Orchestrator:\n".implode("\n", $offenders),
        );
    }

    #[Test]
    public function every_driver_declares_the_provider_it_serves(): void
    {
        // مهايئٌ بلا نبذة يُحلّ بالتخمين، فيرسل جسم Anthropic إلى OpenAI ويُقرأ
        // الإخفاق عطلَ شبكة.
        foreach ($this->phpFiles(app_path('Domains/Orchestrator/Drivers')) as $file) {
            $class = 'App\\Domains\\Orchestrator\\Drivers\\'.basename($file, '.php');

            $this->assertTrue(
                is_subclass_of($class, AiDriver::class),
                "{$class} في مجلد المهايئات ولا ينفّذ العقد.",
            );

            $this->assertNotSame('', app($class)->slug());
        }
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $found = glob($directory.'/{,*/,*/*/,*/*/*/,*/*/*/*/}*.php', GLOB_BRACE);

        return $found === false ? [] : array_values($found);
    }
}
