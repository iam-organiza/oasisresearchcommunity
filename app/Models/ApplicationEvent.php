<?php

namespace App\Models;

use App\Enums\Membership\Grade;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in an application's history: who did what, when, and why.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property Grade|null $grade
 * @property string|null $note
 * @property CarbonImmutable $created_at
 */
#[Fillable(['user_id', 'action', 'grade', 'note'])]
class ApplicationEvent extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'submitted' => 'Submitted the application',
        'resubmitted' => 'Sent the application back with more information',
        'more_information' => 'Asked for more information',
        'recommended' => 'Recommended a different grade',
        'accepted' => 'Accepted the recommended grade',
        'appealed' => 'Appealed for the grade applied for',
        'approved' => 'Approved the application',
        'declined' => 'Declined the application',
        'exemption_approved' => 'Approved the exam exemption',
        'exemption_refused' => 'Refused the exam exemption',
        'fee_waived' => 'Waived the fees',
        'fee_unwaived' => 'Removed the fee waiver',
        'referee_confirmed' => 'The referee confirmed they can vouch',
        'referee_declined' => 'The referee said they cannot vouch',
        'documents_deleted' => 'Deleted the documents after the keeping period',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['grade' => Grade::class, 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
