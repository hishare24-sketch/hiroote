<?php

declare(strict_types=1);

namespace App\Domains\Administration\Actions;

use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Administration\Models\User;
use RuntimeException;

/**
 * تعطيل حساب أو إعادة تفعيله — لا حذف.
 *
 * الحساب المعطَّل يفقد كل صلاحياته (`User::roleIn` تعيد null) ويحتفظ بتاريخه،
 * فسحبُ الوصول لا يتطلب محو الأثر الذي يشرح ما فعله صاحبه.
 */
final readonly class SetUserActive
{
    public function __construct(private RecordAuditEntry $audit) {}

    public function handle(User $user, bool $active, ?User $actor): void
    {
        if ($user->is_active === $active) {
            return;
        }

        // من يعطّل نفسه يُخرج نفسه من اللوحة في اللحظة نفسها، ولا يملك بعدها
        // صلاحية إعادة تفعيلها — فيبقى الباب مغلقًا على من يملك مفتاحه وحده.
        if (! $active && $actor !== null && $actor->id === $user->id) {
            throw new RuntimeException('لا يمكنك تعطيل حسابك أنت.');
        }

        if (! $active && $this->isLastPlatformAdmin($user)) {
            throw new RuntimeException('لا يمكن تعطيل آخر مدير منصة فعّال.');
        }

        $user->forceFill(['is_active' => $active])->save();

        $this->audit->handle(new AuditEntry(
            action: $active ? 'users.activate' : 'users.deactivate',
            auditable: $user,
            section: 'users',
            oldValues: ['الحالة' => $active ? 'معطَّل' : 'فعّال'],
            newValues: ['الحالة' => $active ? 'فعّال' : 'معطَّل'],
            reason: $user->email,
        ));
    }

    /**
     * منصةٌ بلا مدير فعّال لا يستطيع أحدٌ فتحها إلا من قاعدة البيانات — يُمنع
     * تعطيل آخرهم بدل أن يُكتشف الإغلاق بعد وقوعه.
     */
    private function isLastPlatformAdmin(User $user): bool
    {
        if (! $user->is_platform_admin) {
            return false;
        }

        return User::query()
            ->where('is_platform_admin', true)
            ->where('is_active', true)
            ->where('id', '!=', $user->id)
            ->doesntExist();
    }
}
