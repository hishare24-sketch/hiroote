<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * نسخة يومية من قاعدة البيانات.
 *
 * تحمي من **خطأٍ في التطبيق**: هجرةٌ أتلفت، أو حذفٌ بالخطأ، أو استيرادٌ أفسد
 * صفوفًا. ولا تحمي من ضياع الخادم — فهي عليه.
 *
 * وهذا الفرق يُقال هنا وفي الوثيقة، لأن نسخةً تُظنّ حمايةً من الضياع الكامل
 * تُنتج اطمئنانًا كاذبًا: يُكتشف الظنّ يوم يُفقد الخادم، وهو أسوأ يوم لاكتشافه.
 *
 * والفشل صريح: لا تُكتب نسخةٌ فارغة ولا ناقصة تُقرأ نسخةً صالحة.
 */
class BackupDatabase extends Command
{
    protected $signature = 'hiroote:backup {--keep= : كم يومًا تُحفظ}';

    protected $description = 'ينسخ قاعدة البيانات إلى مجلد النسخ';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('backups');

        $name = 'hiroote-'.now()->format('Y-m-d-His').'.sql.gz';
        $path = $disk->path('backups/'.$name);

        $process = Process::fromShellCommandline(
            'pg_dump --no-owner --no-privileges -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "$DB_NAME" | gzip > "$OUT"',
            timeout: 900,
            env: [
                'DB_HOST' => (string) config('database.connections.pgsql.host'),
                'DB_PORT' => (string) config('database.connections.pgsql.port'),
                'DB_USER' => (string) config('database.connections.pgsql.username'),
                'DB_NAME' => (string) config('database.connections.pgsql.database'),
                'PGPASSWORD' => (string) config('database.connections.pgsql.password'),
                'OUT' => $path,
            ],
        );

        $process->run();

        // `gzip` يُنتج ملفًا صالحًا حتى من مدخلٍ فارغ، فرمز الخروج وحده لا يكفي:
        // نسخةٌ من ٢٠ بايت تبدو موجودة في القائمة وتُستعاد فارغة.
        $size = is_file($path) ? (int) filesize($path) : 0;

        if (! $process->isSuccessful() || $size < 1024) {
            @unlink($path);

            $this->components->error('أخفقت النسخة — لم يُكتب ملف.');
            $this->line(trim($process->getErrorOutput()) ?: '  حجمٌ أقل من كيلوبايت.');

            return self::FAILURE;
        }

        $this->components->info("نُسخت: {$name} (".number_format($size / 1024, 1).' ك.ب)');

        $this->prune((int) ($this->option('keep') ?? config('hiroote.backup_retention_days', 7)));

        return self::SUCCESS;
    }

    /**
     * حذف ما تجاوز المدة — والاحتفاظ بالأحدث دائمًا.
     *
     * مدةٌ مضبوطة على صفرٍ سهوًا تمحو كل شيء بما فيه نسخة اليوم، فيبقى المجلد
     * فارغًا ويبدو أن النسخ يعمل.
     */
    private function prune(int $days): void
    {
        $disk = Storage::disk('local');
        $files = collect($disk->files('backups'))
            ->filter(fn (string $file): bool => str_ends_with($file, '.sql.gz'))
            ->sortDesc()
            ->values();

        $cutoff = now()->subDays(max(1, $days))->getTimestamp();
        $removed = 0;

        // الأحدث يُستثنى دائمًا: مجلدٌ بلا نسخةٍ واحدة ليس حالةً مقبولة.
        foreach ($files->skip(1) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->line("  حُذفت {$removed} نسخة تجاوزت {$days} يومًا.");
        }
    }
}
