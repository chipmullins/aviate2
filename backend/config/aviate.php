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

    'uploads' => [
        'max_kilobytes' => 5120,
        'post_image_max_dimension' => 1600,
        'avatar_size' => 400,
    ],
];
