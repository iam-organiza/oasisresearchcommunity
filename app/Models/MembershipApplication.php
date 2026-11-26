<?php

namespace App\Models;

use App\Enums\Membership\ApplicationStatus;
use App\Enums\Membership\DocumentKind;
use App\Enums\Membership\FellowBasis;
use App\Enums\Membership\Grade;
use App\Enums\Membership\InductionEvent;
use App\Enums\Membership\Route;
use App\Membership\ApplicationSteps;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $user_id
 * @property ApplicationStatus $status
 * @property list<string>|null $completed_steps
 * @property Grade|null $grade
 * @property FellowBasis|null $fellow_basis
 * @property Route|null $route
 * @property InductionEvent|null $induction_event
 * @property list<string>|null $claimed_areas
 * @property list<string>|null $exemption_links
 * @property string|null $exemption_status
 * @property Grade|null $decided_grade
 * @property Grade|null $offered_grade
 * @property Grade|null $qualifying_grade
 * @property list<string>|null $offer_reasons
 * @property list<string>|null $flags
 * @property CarbonImmutable|null $offered_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $reviewed_at
 * @property string|null $role_type
 * @property string|null $currently_studying
 * @property string|null $other_evidence
 * @property int|null $decided_by
 * @property CarbonImmutable|null $decided_at
 * @property bool $fee_waived
 * @property CarbonImmutable|null $exam_passed_on
 * @property CarbonImmutable|null $induction_attended_on
 * @property CarbonImmutable|null $declared_at
 * @property CarbonImmutable|null $submitted_at
 */
#[Fillable([
    'title', 'phone', 'state_id', 'city',
    'grade', 'fellow_basis', 'grade_evidence',
    'highest_qualification', 'current_role', 'role_type', 'currently_studying', 'institution', 'department', 'years_experience', 'claimed_areas', 'other_evidence',
    'route', 'exemption_reason', 'exemption_links', 'exam_status', 'exam_passed_on', 'induction_event', 'induction_status', 'induction_attended_on',
    'referee_name', 'referee_email', 'referee_relationship', 'referee_membership_number',
    'directory_consent', 'keep_documents_two_years',
])]
class MembershipApplication extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'completed_steps' => 'array',
            'grade' => Grade::class,
            'fellow_basis' => FellowBasis::class,
            'route' => Route::class,
            'induction_event' => InductionEvent::class,
            'claimed_areas' => 'array',
            'exemption_links' => 'array',
            'decided_grade' => Grade::class,
            'offered_grade' => Grade::class,
            'qualifying_grade' => Grade::class,
            'offer_reasons' => 'array',
            'flags' => 'array',
            'offered_at' => 'datetime',
            'accepted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'decided_at' => 'datetime',
            'fee_waived' => 'boolean',
            'exam_passed_on' => 'date',
            'induction_attended_on' => 'date',
            'directory_consent' => 'boolean',
            'keep_documents_two_years' => 'boolean',
            'declared_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return HasMany<ApplicationPublication, $this>
     */
    public function publications(): HasMany
    {
        return $this->hasMany(ApplicationPublication::class)->orderBy('position');
    }

    /**
     * @return HasMany<ApplicationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class)->orderBy('id');
    }

    /**
     * @return HasMany<ApplicationEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(ApplicationEvent::class)->orderBy('id');
    }

    /**
     * @return HasOne<Membership, $this>
     */
    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    /**
     * Note a step in the application's history.
     */
    public function log(string $action, ?User $actor, ?Grade $grade = null, ?string $note = null): void
    {
        $this->events()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'grade' => $grade,
            'note' => $note,
        ]);
    }

    /**
     * What the Registrar should look at: open applications flagged for a
     * person and not yet checked, appeals, and exam exemption requests.
     *
     * @param  Builder<MembershipApplication>  $query
     */
    public function scopeToCheck(Builder $query): void
    {
        $query->where(fn (Builder $check) => $check
            ->where(fn (Builder $flagged) => $flagged
                ->whereIn('status', [ApplicationStatus::Offered->value, ApplicationStatus::Accepted->value])
                ->whereNull('reviewed_at')
                ->whereJsonLength('flags', '>', 0))
            ->orWhere('status', ApplicationStatus::Submitted->value)
            ->orWhere(fn (Builder $exemption) => $exemption
                ->where('exemption_status', 'requested')
                ->whereNotIn('status', [ApplicationStatus::Approved->value, ApplicationStatus::Declined->value, ApplicationStatus::Draft->value])));
    }

    public function isComplete(string $step): bool
    {
        if ($step === 'documents') {
            return $this->documents()->where('kind', DocumentKind::Cv->value)->exists();
        }

        return in_array($step, $this->completed_steps ?? [], true);
    }

    public function markComplete(string $step): void
    {
        $this->completed_steps = array_values(array_unique([...($this->completed_steps ?? []), $step]));
    }

    /**
     * The steps still to finish before the application can be submitted.
     *
     * @return list<string>
     */
    public function missingSteps(): array
    {
        return array_values(array_filter(
            ApplicationSteps::beforeReview(),
            fn (string $step) => ! $this->isComplete($step),
        ));
    }
}
