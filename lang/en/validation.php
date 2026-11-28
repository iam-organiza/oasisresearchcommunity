<?php

// Only the lines the site words differently; the rest come from Laravel.
// These cover the log-in and forgot/reset password forms, whose rules live in Fortify.
return [
    'custom' => [
        'email' => [
            'required' => 'Enter your email address.',
            'email' => 'Enter an email address like name@example.com.',
        ],
        'password' => [
            'required' => 'Enter your password.',
        ],
    ],
];
