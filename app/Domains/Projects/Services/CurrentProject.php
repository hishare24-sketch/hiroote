<?php

declare(strict_types=1);

namespace App\Domains\Projects\Services;

use App\Domains\Administration\Models\User;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Collection;

/**
 * المشروع النشط في هذه الجلسة — ADR-0003 §4.
 *
 * singleton مرتبط بالطلب: كل استعلام تشغيلي يسأله عن المشروع بدل أن يقرأ
 * الجلسة بنفسه، فيبقى مصدر النطاق واحدًا لا مكررًا في كل متحكم.
 */
final class CurrentProject
{
    public const SESSION_KEY = 'hiroote.current_project';

    private ?Project $project = null;

    /**
     * القائمة مُخزَّنة بمفتاح المستخدم لا مجردة.
     *
     * الحاوية قد تعيش لأكثر من مستخدم في العملية نفسها (اختبارات HTTP، Octane)،
     * وقائمةٌ محفوظة بلا مفتاح تُسلّم مشاريع الأول إلى الثاني.
     *
     * @var array<int, Collection<int, Project>>
     */
    private array $available = [];

    public function set(?Project $project): void
    {
        $this->project = $project;
    }

    public function get(): ?Project
    {
        return $this->project;
    }

    /**
     * المشروع الحالي أو استثناء.
     *
     * يُستدعى من الشاشات التي لا معنى لها بلا مشروع؛ الوصول إليها يمرّ أصلًا
     * بـ middleware يضمن وجوده، فبلوغ الاستثناء يعني خطأ برمجيًا لا حالة مستخدم.
     */
    public function require(): Project
    {
        if ($this->project === null) {
            throw new NoProjectSelectedException;
        }

        return $this->project;
    }

    public function id(): ?int
    {
        return $this->project?->id;
    }

    /**
     * المشاريع التي يملك المستخدم عضوية فيها، مرتبة كما تظهر في المبدّل.
     *
     * @return Collection<int, Project>
     */
    public function availableTo(User $user): Collection
    {
        if (isset($this->available[$user->id])) {
            return $this->available[$user->id];
        }

        $query = Project::query()->active()->orderBy('sort_order')->orderBy('name');

        if (! $user->is_platform_admin) {
            $query->whereHas('members', fn ($members) => $members->where('users.id', $user->id));
        }

        return $this->available[$user->id] = $query->get();
    }
}
