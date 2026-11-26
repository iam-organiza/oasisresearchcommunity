<?php

namespace Tests\Feature\Membership;

use App\Enums\Membership\ApplicationStatus;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RetentionAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * An application with one stored document, decided some time ago.
     */
    private function withDocument(ApplicationStatus $status, \DateTimeInterface $decidedAt, bool $keepTwoYears = false): MembershipApplication
    {
        $application = User::factory()->create()->membershipApplications()->create(['grade' => 'member', 'keep_documents_two_years' => $keepTwoYears]);
        $application->forceFill(['status' => $status, 'decided_at' => $decidedAt])->save();

        $path = "applications/{$application->id}/cv.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4');
        $application->documents()->create(['kind' => 'cv', 'path' => $path, 'original_name' => 'cv.pdf', 'mime_type' => 'application/pdf', 'size' => 8]);

        return $application;
    }

    public function test_declined_documents_are_deleted_after_their_keeping_period_only(): void
    {
        Storage::fake('local');

        $dueShort = $this->withDocument(ApplicationStatus::Declined, now()->subMonths(7));
        $keptLonger = $this->withDocument(ApplicationStatus::Declined, now()->subMonths(7), keepTwoYears: true);
        $dueLong = $this->withDocument(ApplicationStatus::Declined, now()->subMonths(25), keepTwoYears: true);
        $recent = $this->withDocument(ApplicationStatus::Declined, now()->subMonths(2));
        $approved = $this->withDocument(ApplicationStatus::Approved, now()->subYears(3));

        $this->artisan('membership:prune-documents')->assertSuccessful();

        foreach ([$dueShort, $dueLong] as $application) {
            $this->assertSame(0, $application->documents()->count());
            Storage::disk('local')->assertMissing("applications/{$application->id}/cv.pdf");
            $this->assertSame('documents_deleted', $application->events()->reorder('id', 'desc')->value('action'));
        }

        foreach ([$keptLonger, $recent, $approved] as $application) {
            $this->assertSame(1, $application->documents()->count());
            Storage::disk('local')->assertExists("applications/{$application->id}/cv.pdf");
        }
    }

    public function test_a_dry_run_deletes_nothing(): void
    {
        Storage::fake('local');
        $application = $this->withDocument(ApplicationStatus::Declined, now()->subYear());

        $this->artisan('membership:prune-documents', ['--dry-run' => true])
            ->expectsOutputToContain('Would delete 1 file(s)')
            ->assertSuccessful();

        $this->assertSame(1, $application->documents()->count());
    }

    public function test_the_clean_up_runs_every_night(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'membership:prune-documents'));

        $this->assertNotNull($event);
        $this->assertSame('30 2 * * *', $event->expression);
    }

    public function test_the_privacy_policy_is_published_and_linked(): void
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertSee('Privacy Policy')
            ->assertSee('Nigeria Data Protection Act 2023')
            ->assertSee('6 months after the decision')
            ->assertSee(config('oasis.support_email'));

        $this->get(route('home'))->assertSee(route('privacy'), false);

        // Terms & Conditions still renders through the shared document page, with its title decoded once.
        $this->get(route('terms'))
            ->assertOk()
            ->assertSee('<title>Terms &amp; Conditions', false)
            ->assertSee('Eligibility and Membership');
    }
}
