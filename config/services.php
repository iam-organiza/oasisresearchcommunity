<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Paystack, on the same account as OASIS Journals (OASIS, 2026-11-27).
    | Without a secret key, online payment is switched off and members see
    | how to pay by bank transfer instead. Dollar checkout stays off until
    | Paystack enables USD on the account, as on OASIS Journals.
    |
    | Paystack's charge is added to what the member pays (OASIS, 2026-11-27).
    | The account's own "pass charges to customer" setting would also change
    | OASIS Journals, so the site adds it itself, at Paystack's published
    | rates (support.paystack.com, checked 2026-11-27): naira 1.5% + ₦100,
    | the ₦100 waived under ₦2,500, capped at ₦2,000; dollars 3.9%. A naira
    | payment from a foreign card costs OASIS more (3.9% + ₦100); the
    | member is charged the local rate.
    */
    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'usd_enabled' => (bool) env('PAYSTACK_USD_ENABLED', false),
        'base_url' => 'https://api.paystack.co',
        'fees' => [
            'ngn' => ['percent' => 1.5, 'flat' => 100, 'flat_waived_below' => 2500, 'cap' => 2000],
            'usd' => ['percent' => 3.9],
        ],
    ],

];
