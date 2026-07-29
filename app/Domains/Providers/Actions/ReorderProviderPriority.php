<?php

declare(strict_types=1);

namespace App\Domains\Providers\Actions;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Providers\Models\AiProvider;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * إعادة ترتيب أولوية المزودين (وثيقة التصميم §8 — ترتيب الأولوية).
 */
final readonly class ReorderProviderPriority
{
    public function __construct(private RecordAuditEntry $audit) {}

    /**
     * @param  list<int>  $orderedIds  Provider ids in the desired priority order.
     */
    public function handle(array $orderedIds): void
    {
        $providers = AiProvider::query()->get();

        $known = $providers->pluck('id')->sort()->values()->all();
        $given = collect($orderedIds)->sort()->values()->all();

        if ($known !== $given) {
            throw new InvalidArgumentException('قائمة الترتيب يجب أن تشمل كل المزودين مرة واحدة.');
        }

        $before = $providers->sortBy('priority')->pluck('name')->values()->all();

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $id) {
                AiProvider::query()->whereKey($id)->update(['priority' => $index + 1]);
            }
        });

        $after = AiProvider::query()->orderBy('priority')->pluck('name')->values()->all();

        $this->audit->handle(new AuditEntry(
            action: 'providers.reorder',
            section: 'providers',
            oldValues: ['priority_order' => $before],
            newValues: ['priority_order' => $after],
        ));
    }
}
