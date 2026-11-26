<?php

namespace Tests\Feature\Membership;

use App\Enums\Membership\Grade;
use App\Enums\Membership\Route;
use App\Membership\GradeRanking;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeRankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $answers
     */
    private function application(array $answers, int $publications = 0): MembershipApplication
    {
        $application = User::factory()->create()->membershipApplications()->create([
            'grade' => Grade::Fellow,
            'highest_qualification' => 'masters',
            'role_type' => 'lecturer',
            'currently_studying' => 'no',
            'years_experience' => 6,
            'claimed_areas' => ['methodology', 'supervision'],
            'route' => Route::Exam,
            'exam_status' => 'will_take',
            ...$answers,
        ]);
        for ($i = 0; $i < $publications; $i++) {
            $application->publications()->create(['title' => "Paper {$i}", 'venue' => 'OASIS Journal', 'year' => 2020, 'position' => $i]);
        }

        return $application->refresh();
    }

    public function test_fellow_needs_postgraduate_or_a_research_role_senior_work_publications_and_years(): void
    {
        $fellow = GradeRanking::assess($this->application([], publications: 3));
        $this->assertSame(Grade::Fellow, $fellow['qualifies']);
        $this->assertSame(Grade::Fellow, $fellow['offer']);
        $this->assertSame([], $fellow['reasons']);
        $this->assertContains('fellow', $fellow['flags'], 'Fellow offers are for a person to check.');

        // A doctorate with 5 publications stands in for the years.
        $this->assertSame(Grade::Fellow, GradeRanking::assess($this->application(['highest_qualification' => 'phd', 'years_experience' => 2], publications: 5))['qualifies']);

        // Public service with research counts as a research role (OASIS, 27 Nov 2026).
        $this->assertSame(Grade::Fellow, GradeRanking::assess($this->application(['highest_qualification' => 'hnd', 'role_type' => 'public_service'], publications: 3))['qualifies']);
        $this->assertSame(Grade::Member, GradeRanking::assess($this->application(['highest_qualification' => 'hnd', 'role_type' => 'other'], publications: 3))['qualifies']);
    }

    public function test_a_lower_offer_says_what_is_missing(): void
    {
        $result = GradeRanking::assess($this->application(['claimed_areas' => ['methodology'], 'years_experience' => 4], publications: 2));

        $this->assertSame(Grade::Member, $result['qualifies']);
        $this->assertSame(Grade::Member, $result['offer']);
        $this->assertSame([
            'For Fellow we look for experience supervising students, editing or reviewing for journals; '
            .'3 or more publications (you listed 2); and 5 or more years of research experience (you gave 4), or a doctorate with 5 or more publications.',
        ], $result['reasons']);
    }

    public function test_member_and_graduate(): void
    {
        $member = ['grade' => Grade::Member, 'highest_qualification' => 'bachelors', 'role_type' => 'researcher', 'years_experience' => 2, 'claimed_areas' => ['methodology']];

        $this->assertSame(Grade::Member, GradeRanking::assess($this->application($member, publications: 1))['offer']);
        // Supervising, editing or reviewing stands in for a publication.
        $this->assertSame(Grade::Member, GradeRanking::assess($this->application([...$member, 'claimed_areas' => ['reviewing']]))['offer']);

        $graduate = GradeRanking::assess($this->application([...$member, 'years_experience' => 1], publications: 1));
        $this->assertSame(Grade::Graduate, $graduate['offer']);
        $this->assertSame(['For Member we look for 2 or more years of research experience (you gave 1).'], $graduate['reasons']);
    }

    public function test_students_and_applicants_no_grade_fits(): void
    {
        $student = GradeRanking::assess($this->application(['grade' => Grade::Student, 'highest_qualification' => 'secondary', 'currently_studying' => 'undergraduate', 'role_type' => 'student', 'years_experience' => 0]));
        $this->assertSame(Grade::Student, $student['offer']);
        $this->assertNotContains('no_grade_fits', $student['flags']);

        // An MSc student who already has a first degree is a graduate.
        $this->assertSame(Grade::Graduate, GradeRanking::assess($this->application(['highest_qualification' => 'bachelors', 'currently_studying' => 'masters', 'years_experience' => 0]))['qualifies']);

        // An OND and not studying: offered Student Member, to learn with OASIS (27 Nov 2026).
        $ond = GradeRanking::assess($this->application(['grade' => Grade::Graduate, 'highest_qualification' => 'ond', 'role_type' => 'other', 'years_experience' => 0]));
        $this->assertSame(Grade::Student, $ond['offer']);
        $this->assertContains('no_grade_fits', $ond['flags']);
        $this->assertStringStartsWith('Membership grades start from a first degree or an HND.', $ond['reasons'][0]);
    }

    public function test_the_offer_is_never_above_what_was_asked_for(): void
    {
        $result = GradeRanking::assess($this->application(['grade' => Grade::Member], publications: 3));

        $this->assertSame(Grade::Fellow, $result['qualifies']);
        $this->assertSame(Grade::Member, $result['offer']);
        $this->assertStringContainsString('also meet the criteria for Fellow', $result['reasons'][0]);
    }

    public function test_cases_for_a_person_are_flagged(): void
    {
        $flags = GradeRanking::assess($this->application([
            'grade' => Grade::Member, 'other_evidence' => 'Ran three community surveys for the state ministry.',
            'route' => Route::Exemption, 'exam_status' => null,
        ], publications: 1))['flags'];

        $this->assertSame(['other_evidence', 'exemption'], $flags);
        $this->assertSame(['certification'], GradeRanking::assess($this->application(['grade' => Grade::Member, 'route' => Route::CertificationProgramme, 'exam_status' => null], publications: 1))['flags']);
        $this->assertSame(['exam_passed'], GradeRanking::assess($this->application(['grade' => Grade::Member, 'exam_status' => 'passed'], publications: 1))['flags']);
    }
}
