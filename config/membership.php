<?php

/*
| Membership grades: fees in naira and the entry criteria shown to applicants.
|
| Decided with OASIS on 2026-11-26: four individual grades (Fellow, Member,
| Graduate, Student); Member replaces the old Associate, Full and Senior
| grades, and OASIS gave the criteria below. induction_fee is the one-off
| fee at the start (OASIS, 2026-11-27) and annual_dues the yearly
| subscription (2026-11-26), both in naira. The exam fee is a flat 20,000,
| taken off the induction fee for those who pass (not for those who accept
| a lower grade after failing).
|
| Since 2026-11-28 these are only the STARTING figures: the migration copies
| them into the price_settings table, and staff change fees on the Fees
| screen (App\Account\Fees reads them). Changing a figure here does nothing
| once that migration has run.
|
| Upgrades (OASIS, 2026-11-27): within the first year on a grade, the
| induction-fee difference plus upgrade_percent of it; after one year, the
| new grade's induction fee less upgrade_discount_1y percent; after two
| years less upgrade_discount_2y; after three, the full fee. A promotion
| (offered for three years of consistent training) is the new grade's fee
| less promotion_discount.
| dollar_margin_percent: added to the naira price converted at the day's
| rate, then rounded up, so a dollar price is never cheaper (2026-11-27).
| Institutional Membership comes in a later phase.
*/
return [
    'exam_fee' => 20000,
    'upgrade_percent' => 10,
    'upgrade_discount_1y' => 20,
    'upgrade_discount_2y' => 15,
    'promotion_discount' => 20,
    'dollar_margin_percent' => 5,

    'grades' => [
        'fellow' => [
            'induction_fee' => 250000,
            'annual_dues' => 60000,
            'criteria' => [
                'An MSc or PhD, or',
                'A lecturer, research consultant, NGO professional, public servant or private-sector professional (for example a lawyer or doctor) whose role involves research,',
                'with evidence of leading, completing and publishing research, supervising students, or editing or reviewing for journals (or the equivalent in your field).',
                'Usually 5+ years of experience, but this is not required if you meet everything else.',
            ],
        ],
        'member' => [
            'induction_fee' => 150000,
            'annual_dues' => 50000,
            'criteria' => [
                'A BSc or MSc, with 2+ years of experience leading, completing or publishing research, supervising, or editing or reviewing for journals.',
            ],
        ],
        'graduate' => [
            'induction_fee' => 100000,
            'annual_dues' => 40000,
            'criteria' => [
                'A BSc or HND holder, or a postgraduate student, without evidence of research experience yet.',
            ],
        ],
        'student' => [
            'induction_fee' => 50000,
            'annual_dues' => 20000,
            'criteria' => [
                'A BSc, college, polytechnic or MSc student.',
            ],
        ],
    ],
];
