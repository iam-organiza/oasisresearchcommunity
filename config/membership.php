<?php

/*
| Membership grades: fees in naira and the entry criteria shown to applicants.
|
| Decided with OASIS on 2026-11-26: four individual grades (Fellow, Member,
| Graduate, Student); Member replaces the old Associate, Full and Senior
| grades. Member uses the old Full Member fees and criteria until OASIS
| confirms its own. Institutional Membership comes in a later phase.
*/
return [
    'grades' => [
        'fellow' => [
            'induction_fee' => 125000,
            'annual_dues' => 25000,
            'criteria' => [
                'A PhD in any field, or',
                'Senior lecturer rank or above, or',
                'Research consultant with experience you can evidence on your CV, or',
                'A leader whose work relies on research (for example a chief executive, legislator, or current or former public office holder), or',
                'At least 10 years of research experience with at least 12 publications.',
            ],
        ],
        'member' => [
            'induction_fee' => 55000,
            'annual_dues' => 10000,
            'criteria' => [
                "A Bachelor's degree, the OASIS membership exam and at least 2 years of research experience, or",
                "A Master's degree, or",
                'Assistant lecturer rank or above, or',
                'At least 3 years of research experience with publications.',
            ],
        ],
        'graduate' => [
            'induction_fee' => 25000,
            'annual_dues' => 5000,
            'criteria' => [
                'Passed the OASIS membership exam, but not yet meeting the research experience needed for Member (for example, no publications yet).',
            ],
        ],
        'student' => [
            'induction_fee' => 15000,
            'annual_dues' => 2000,
            'criteria' => [
                'Passed the OASIS membership exam while still completing an undergraduate programme.',
            ],
        ],
    ],
];
