<?php

declare(strict_types=1);

namespace App\Domains\Administration\DTOs;

use Illuminate\Database\Eloquent\Model;

/**
 * The contract for writing one audit entry (وثيقة 03 §2 — DTOs للعقود بين الطبقات).
 */
final readonly class AuditEntry
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __construct(
        public string $action,
        public ?Model $auditable = null,
        public ?string $section = null,
        public ?array $oldValues = null,
        public ?array $newValues = null,
        public ?string $reason = null,
    ) {}
}
