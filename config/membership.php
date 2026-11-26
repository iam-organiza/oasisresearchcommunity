<?php

/*
| Membership grades: fees in naira and the entry criteria shown to applicants.
|
| Decided with OASIS on 2026-11-26: four individual grades (Fellow, Member,
| Graduate, Student); Member replaces the old Associate, Full and Senior
| grades, and OASIS gave the criteria below. The fees are still the old ones
| while OASIS confirms whether its new yearly subscriptions (Fellow 60,000,
| Member 50,000, Graduate 40,000, Student 20,000) replace both the induction
| fee and the dues; fees move to an admin screen with the payments phase.
| Institutional Membership comes in a later phase.
*/
return [
    'grades' => [
        'fellow' => [
            'induction_fee' => 125000,
            'annual_dues' => 25000,
            'criteria' => [
                'An MSc or PhD, or',
                'A lecturer, research consultant, NGO professional, public servant or private-sector professional (for example a lawyer or doctor) whose role involves research,',
                'with evidence of leading, completing and publishing research, supervising students, or editing or reviewing for journals (or the equivalent in your field).',
                'Usually 5+ years of experience, but this is not required if you meet everything else.',
            ],
        ],
        'member' => [
            'induction_fee' => 55000,
            'annual_dues' => 10000,
            'criteria' => [
                'A BSc or MSc, with 2+ years of experience leading, completing or publishing research, supervising, or editing or reviewing for journals.',
            ],
        ],
        'graduate' => [
            'induction_fee' => 25000,
            'annual_dues' => 5000,
            'criteria' => [
                'A BSc or HND holder, or a postgraduate student, without evidence of research experience yet.',
            ],
        ],
        'student' => [
            'induction_fee' => 15000,
            'annual_dues' => 2000,
            'criteria' => [
                'A BSc, college, polytechnic or MSc student.',
            ],
        ],
    ],
];
