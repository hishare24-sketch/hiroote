<?php

declare(strict_types=1);

namespace App\Domains\Administration\Actions;

use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * إنشاء حساب مشغّل أو تعديل بياناته.
 *
 * **الدور هنا افتراضٌ للعضوية القادمة لا صلاحية نافذة.** الصلاحية تُحلّ لكل
 * مشروع من `project_user` (ADR-0003 §3)، فحسابٌ بدور «مدير النظام» بلا عضوية
 * لا يرى شيئًا. والشاشة تقولها صراحةً كي لا يُمنح دورٌ ثم يُنتظر أثرٌ لا يأتي.
 */
final readonly class SaveUser
{
    public function __construct(private RecordAuditEntry $audit) {}

    /**
     * @param  array{name: string, email: string, role: Role, password?: string|null, is_platform_admin?: bool}  $data
     */
    public function handle(?User $user, array $data): User
    {
        $creating = $user === null;
        $before = $creating ? null : [
            'الاسم' => $user->name,
            'البريد' => $user->email,
            'الدور الافتراضي' => $user->role->label(),
            'مدير منصة' => $user->is_platform_admin ? 'نعم' : 'لا',
        ];

        $user ??= new User;

        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_platform_admin' => $data['is_platform_admin'] ?? $user->is_platform_admin ?? false,
        ]);

        // كلمة المرور الفارغة تعني «اتركها» لا «امحُها»: من يصحّح اسمًا لا ينوي
        // إخراج صاحبه من حسابه.
        $password = $data['password'] ?? null;

        if (is_string($password) && $password !== '') {
            $user->forceFill(['password' => Hash::make($password)]);
        }

        if ($creating) {
            $user->forceFill([
                'is_active' => true,
                // لا إرسال بريد في هذه المرحلة، فلا يُدَّعى تحقّقٌ لم يحدث:
                // الحساب يُنشأ بكلمة مرور يسلّمها المشرف يدًا بيد.
                'email_verified_at' => null,
            ]);
        }

        $user->save();

        $this->audit->handle(new AuditEntry(
            action: $creating ? 'users.create' : 'users.update',
            auditable: $user,
            section: 'users',
            oldValues: $before,
            // كلمة المرور لا تُسجَّل ولا يُسجَّل أنها تغيّرت بقيمتها.
            newValues: [
                'الاسم' => $user->name,
                'البريد' => $user->email,
                'الدور الافتراضي' => $user->role->label(),
                'مدير منصة' => $user->is_platform_admin ? 'نعم' : 'لا',
                'كلمة المرور' => is_string($password) && $password !== '' ? 'غُيّرت' : 'كما هي',
            ],
        ));

        return $user;
    }
}
