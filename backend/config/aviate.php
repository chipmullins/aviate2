<?php

return [

    /*
    | Only people with an email at one of these airline domains can register
    | or change their email. Override with a comma-separated list in
    | AVIATE_ALLOWED_EMAIL_DOMAINS (e.g. for a staging environment).
    */
    'allowed_email_domains' => array_values(array_filter(array_map(
        fn (string $d) => strtolower(trim($d)),
        explode(',', (string) env(
            'AVIATE_ALLOWED_EMAIL_DOMAINS',
            'united.com,delta.com,aa.com,allegiant.com,suncountry.com,jetblue.com,southwest.com'
        ))
    ))),

    // Friendly names shown on profiles and in error messages.
    'airlines' => [
        'united.com' => 'United Airlines',
        'delta.com' => 'Delta Air Lines',
        'aa.com' => 'American Airlines',
        'allegiant.com' => 'Allegiant Air',
        'suncountry.com' => 'Sun Country Airlines',
        'jetblue.com' => 'JetBlue',
        'southwest.com' => 'Southwest Airlines',
    ],

    /*
    | Public demo mode (DEMO_MODE=true). Visitors can sign in as the sample crew with one click,
    | the sample accounts can't be changed or closed, and the database and uploads are wiped and
    | re-seeded every night at DEMO_RESET_AT (in DEMO_TIME_ZONE).
    */
    'demo' => [
        'enabled' => (bool) env('DEMO_MODE', false),
        'reset_at' => env('DEMO_RESET_AT', '04:00'),
        'time_zone' => env('DEMO_TIME_ZONE', 'America/New_York'),
        // Shown as "Try the demo" buttons on the login page, in this order.
        'featured' => [
            ['username' => 'maya.chen', 'blurb' => 'Delta flight attendant with a busy feed, friend requests and messages'],
            ['username' => 'marcus.webb', 'blurb' => 'United pilot. Sign in as him in another window to see live messages'],
        ],
    ],

    'uploads' => [
        'max_kilobytes' => 5120,
        'post_image_max_dimension' => 1600,
        'avatar_size' => 400,
    ],
];
