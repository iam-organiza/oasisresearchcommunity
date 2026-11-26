<?php

namespace App\Models;

use App\Enums\Membership\DocumentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded CV, certificate or other evidence. Stored on the private
 * `local` disk; only the applicant and membership staff can download it.
 *
 * @property int $id
 * @property int $membership_application_id
 * @property DocumentKind $kind
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 */
#[Fillable(['kind', 'path', 'original_name', 'mime_type', 'size'])]
class ApplicationDocument extends Model
{
    protected static function booted(): void
    {
        static::deleted(function (ApplicationDocument $document): void {
            Storage::disk('local')->delete($document->path);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => DocumentKind::class];
    }

    /**
     * @return BelongsTo<MembershipApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(MembershipApplication::class, 'membership_application_id');
    }
}
