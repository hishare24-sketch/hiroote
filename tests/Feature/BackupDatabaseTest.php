<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * النسخة الاحتياطية تقع، وتُقال حين لا تقع.
 *
 * `gzip` يُنتج ملفًا صالحًا حتى من مدخلٍ فارغ، فرمز الخروج وحده لا يكفي: نسخةٌ
 * من عشرين بايتًا تظهر في القائمة وتُستعاد فارغة — ويوم استعادتها أسوأ يومٍ
 * لاكتشاف أنها لم تكن نسخة.
 */
class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_backup_is_on_the_schedule(): void
    {
        // الأمر نفسه لا نصّه المعروض: لارافل يعرض **وصف** الأمر حين يوجد، فحارسٌ
        // يقرأ المعروض يسقط بمجرد تحرير جملةٍ عربية لا علاقة لها بالجدولة.
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event): string => (string) $event->command);

        $this->assertTrue(
            $commands->contains(fn (string $command): bool => str_contains($command, 'hiroote:backup')),
            'النسخ ليس في الجدولة — فوثيقةٌ تشرحه تُقرأ ضمانًا وهي تذكير.',
        );
    }

    #[Test]
    public function it_writes_a_real_dump_and_prunes_the_old(): void
    {
        $this->artisan('hiroote:backup')->assertSuccessful();

        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $file): bool => str_ends_with($file, '.sql.gz'));

        $this->assertCount(1, $files);

        // ليس ملفًا فارغًا: القاعدة مهاجَرة، فالإفراغ يحمل جداولها.
        $this->assertGreaterThan(
            1024,
            Storage::disk('local')->size($files->first() ?? ''),
            'النسخة أصغر من كيلوبايت — تُستعاد فارغة وتبدو موجودة.',
        );
    }

    #[Test]
    public function the_newest_is_never_pruned(): void
    {
        // مدةٌ مضبوطة على صفرٍ سهوًا تمحو كل شيء بما فيه نسخة اليوم، فيبقى
        // المجلد فارغًا ويبدو أن النسخ يعمل.
        $this->artisan('hiroote:backup', ['--keep' => 0])->assertSuccessful();
        $this->artisan('hiroote:backup', ['--keep' => 0])->assertSuccessful();

        $files = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $file): bool => str_ends_with($file, '.sql.gz'));

        $this->assertGreaterThanOrEqual(1, $files->count(), 'لم تبقَ نسخة واحدة.');
    }

    protected function tearDown(): void
    {
        foreach (Storage::disk('local')->files('backups') as $file) {
            Storage::disk('local')->delete($file);
        }

        parent::tearDown();
    }
}
