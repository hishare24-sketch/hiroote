<?php

declare(strict_types=1);

namespace App\Domains\Providers\Models;

use App\Domains\Administration\Models\User;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * مفتاح مزود مشفر — وثيقة 02 §12: لا يظهر كاملًا في الواجهة أو السجلات،
 * ويدعم الإبطال وتاريخ آخر استخدام.
 *
 * @property int $id
 * @property int $provider_id
 * @property string $label
 * @property string $api_key
 * @property string $key_hint
 * @property bool $is_active
 * @property Carbon|null $last_used_at
 * @property int|null $created_by
 */
#[Hidden(['api_key'])]
class AiProviderCredential extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Laravel encrypts with APP_KEY at rest; the column never holds plaintext.
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'provider_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
