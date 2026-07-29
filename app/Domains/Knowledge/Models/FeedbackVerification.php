<?php

declare(strict_types=1);

namespace App\Domains\Knowledge\Models;

use App\Domains\Administration\Models\User;
use App\Domains\Knowledge\Enums\VerificationOutcome;
use App\Domains\Projects\Models\Concerns\BelongsToProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * محضر تحقق ميداني واحد — ماذا فعل الموظف بوصفه مستخدمًا وماذا وجد.
 *
 * @property int $id
 * @property int $project_id
 * @property int $knowledge_feedback_id
 * @property int|null $screen_id
 * @property int|null $verified_by
 * @property VerificationOutcome $outcome
 * @property string $steps
 * @property string|null $finding
 * @property Carbon $created_at
 * @property-read User|null $verifier
 * @property-read KnowledgeScreen|null $screen
 */
class FeedbackVerification extends Model
{
    use BelongsToProject;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'outcome' => VerificationOutcome::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<KnowledgeFeedback, $this> */
    public function feedback(): BelongsTo
    {
        return $this->belongsTo(KnowledgeFeedback::class, 'knowledge_feedback_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return BelongsTo<KnowledgeScreen, $this> */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(KnowledgeScreen::class, 'screen_id');
    }
}
