<?php

namespace App\Membership;

use App\Enums\Membership\Grade;
use App\Enums\Membership\Route;
use App\Models\MembershipApplication;

/**
 * Works out the grade an applicant qualifies for from their answers, and
 * the grade to offer them (OASIS, 2026-11-27: the site reviews
 * applications itself; thresholds confirmed as drafted). The first rule
 * that matches wins:
 *
 * - Student Member: studying now, without a first degree or HND. Someone
 *   with neither a degree nor studies (an OND or secondary school) is
 *   offered Student Member too, to learn research with OASIS.
 * - Fellow: a master's or doctorate, or a role that involves research;
 *   supervising, editing or reviewing; 3+ publications; and 5+ years of
 *   research (or a doctorate with 5+ publications instead of the years).
 * - Member: a first degree or HND or higher, 2+ years of research, and a
 *   publication or supervising, editing or reviewing.
 * - Graduate Member: a first degree or HND.
 *
 * Every grade above Student Member needs a first degree or an HND.
 *
 * The offer is the lower of the grade asked for and the grade qualified
 * for. Answers the site cannot judge (the "anything else" box) and the
 * cases that need a person are flagged for the Registrar, who can correct
 * the offer.
 */
final class GradeRanking
{
    public const FELLOW_PUBLICATIONS = 3;

    public const FELLOW_YEARS = 5;

    /** A doctorate with this many publications stands in for the Fellow years. */
    public const FELLOW_DOCTORATE_PUBLICATIONS = 5;

    public const MEMBER_YEARS = 2;

    public const MEMBER_PUBLICATIONS = 1;

    /** The activities the Fellow and Member criteria name. */
    public const SENIOR_ACTIVITIES = ['supervision', 'editorial', 'reviewing'];

    /** A first degree or HND, or higher. */
    private const DEGREES = ['hnd', 'bachelors', 'masters', 'phd'];

    private const POSTGRADUATE = ['masters', 'phd'];

    private const NOT_RESEARCH_ROLES = ['student', 'other'];

    /** Why the Registrar should look, as shown on their queue. */
    public const FLAGS = [
        'fellow' => 'Offered Fellow',
        'other_evidence' => 'Added other evidence',
        'no_grade_fits' => 'No grade fits; offered Student Member',
        'exemption' => 'Asks to skip the exam',
        'certification' => 'Certification Programme',
        'exam_passed' => 'Says they passed the exam before',
    ];

    /**
     * @return array{qualifies: Grade, offer: Grade, reasons: list<string>, flags: list<string>}
     */
    public static function assess(MembershipApplication $application): array
    {
        $applied = $application->grade ?? Grade::Student;
        [$qualifies, $noGradeFits] = self::qualifyingGrade($application);
        $offer = self::rank($applied) >= self::rank($qualifies) ? $applied : $qualifies;

        $reasons = [];
        if ($noGradeFits) {
            $reasons[] = 'Membership grades start from a first degree or an HND. As a Student Member you take the exam at Student level, learn research with us, and can move up as your qualifications grow.';
        } elseif ($offer !== $applied) {
            $reasons = self::missing($application, $applied);
        }
        if (self::rank($qualifies) < self::rank($applied)) {
            $reasons[] = "Your answers also meet the criteria for {$qualifies->label()}. You asked for {$applied->label()}, so that is your offer; you can move up later.";
        }

        $flags = [];
        if ($offer === Grade::Fellow) {
            $flags[] = 'fellow';
        }
        if (filled($application->other_evidence)) {
            $flags[] = 'other_evidence';
        }
        if ($noGradeFits) {
            $flags[] = 'no_grade_fits';
        }
        if ($application->route === Route::Exemption) {
            $flags[] = 'exemption';
        }
        if ($application->route === Route::CertificationProgramme) {
            $flags[] = 'certification';
        }
        if ($application->route === Route::Exam && $application->exam_status === 'passed') {
            $flags[] = 'exam_passed';
        }

        return ['qualifies' => $qualifies, 'offer' => $offer, 'reasons' => $reasons, 'flags' => $flags];
    }

    /**
     * @return array{0: Grade, 1: bool} the grade, and whether no grade fits (so Student Member is a fallback)
     */
    private static function qualifyingGrade(MembershipApplication $application): array
    {
        $degree = in_array($application->highest_qualification, self::DEGREES, true);
        $studying = ! in_array($application->currently_studying, [null, '', 'no'], true);

        if (! $degree) {
            return [Grade::Student, ! $studying];
        }
        if (self::meetsFellow($application)) {
            return [Grade::Fellow, false];
        }
        if (self::meetsMember($application)) {
            return [Grade::Member, false];
        }

        return [Grade::Graduate, false];
    }

    private static function meetsFellow(MembershipApplication $application): bool
    {
        $publications = self::publications($application);
        $doctorate = $application->highest_qualification === 'phd';

        return (in_array($application->highest_qualification, self::POSTGRADUATE, true) || self::researchRole($application))
            && self::seniorActivities($application) !== []
            && $publications >= self::FELLOW_PUBLICATIONS
            && ((int) $application->years_experience >= self::FELLOW_YEARS || ($doctorate && $publications >= self::FELLOW_DOCTORATE_PUBLICATIONS));
    }

    private static function meetsMember(MembershipApplication $application): bool
    {
        return (int) $application->years_experience >= self::MEMBER_YEARS
            && (self::publications($application) >= self::MEMBER_PUBLICATIONS || self::seniorActivities($application) !== []);
    }

    /**
     * What the applicant would need for the grade they asked for, in plain words.
     *
     * @return list<string>
     */
    private static function missing(MembershipApplication $application, Grade $applied): array
    {
        $publications = self::publications($application);
        $years = (int) $application->years_experience;
        $degree = in_array($application->highest_qualification, self::DEGREES, true);
        $needs = [];

        if ($applied === Grade::Fellow) {
            if (! in_array($application->highest_qualification, self::POSTGRADUATE, true) && ! self::researchRole($application)) {
                $needs[] = "a master's degree or doctorate, or a role that involves research";
            }
            if (self::seniorActivities($application) === []) {
                $needs[] = 'experience supervising students, editing or reviewing for journals';
            }
            if ($publications < self::FELLOW_PUBLICATIONS) {
                $needs[] = self::FELLOW_PUBLICATIONS.' or more publications (you listed '.$publications.')';
            }
            if ($years < self::FELLOW_YEARS && ! ($application->highest_qualification === 'phd' && $publications >= self::FELLOW_DOCTORATE_PUBLICATIONS)) {
                $needs[] = self::FELLOW_YEARS." or more years of research experience (you gave {$years}), or a doctorate with ".self::FELLOW_DOCTORATE_PUBLICATIONS.' or more publications';
            }
        }

        if ($applied === Grade::Member) {
            if (! $degree) {
                $needs[] = 'a first degree or an HND';
            }
            if ($years < self::MEMBER_YEARS) {
                $needs[] = self::MEMBER_YEARS." or more years of research experience (you gave {$years})";
            }
            if ($publications < self::MEMBER_PUBLICATIONS && self::seniorActivities($application) === []) {
                $needs[] = 'a publication, or experience supervising, editing or reviewing';
            }
        }

        if ($applied === Grade::Graduate && ! $degree) {
            $needs[] = 'a first degree or an HND';
        }

        return $needs === [] ? [] : ["For {$applied->label()} we look for ".self::sentence($needs).'.'];
    }

    private static function publications(MembershipApplication $application): int
    {
        return $application->relationLoaded('publications')
            ? $application->publications->count()
            : $application->publications()->count();
    }

    /**
     * @return list<string>
     */
    private static function seniorActivities(MembershipApplication $application): array
    {
        return array_values(array_intersect($application->claimed_areas ?? [], self::SENIOR_ACTIVITIES));
    }

    private static function researchRole(MembershipApplication $application): bool
    {
        return $application->role_type !== null
            && array_key_exists($application->role_type, ApplicationSteps::ROLE_TYPES)
            && ! in_array($application->role_type, self::NOT_RESEARCH_ROLES, true);
    }

    /** 0 is the highest grade. */
    private static function rank(Grade $grade): int
    {
        return (int) array_search($grade, Grade::cases(), true);
    }

    /**
     * @param  list<string>  $parts
     */
    private static function sentence(array $parts): string
    {
        if (count($parts) === 1) {
            return $parts[0];
        }
        $last = array_pop($parts);

        return implode('; ', $parts).'; and '.$last;
    }
}
