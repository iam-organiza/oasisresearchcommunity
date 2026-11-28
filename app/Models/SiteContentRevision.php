<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A section of the public site as it stood after one save or restore.
 *
 * @property int $id
 * @property string $key
 * @property array<string, mixed> $value
 * @property int|null $user_id
 * @property string $action
 * @property int|null $restored_from_id
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
#[Fillable(['key', 'value', 'user_id', 'action', 'restored_from_id'])]
class SiteContentRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
