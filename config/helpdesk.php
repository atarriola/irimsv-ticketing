<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded Administrator
    |--------------------------------------------------------------------------
    |
    | The LRMIS account that `php artisan db:seed` creates on the Administrator
    | user type so that a fresh install has someone who can administer the
    | helpdesk. The seeder leaves an account that already has this username
    | alone, so changing these values never resets an existing password.
    |
    */

    'administrator' => [
        'username' => env('HELPDESK_ADMIN_USERNAME', 'helpdesk.admin'),
        'password' => env('HELPDESK_ADMIN_PASSWORD', 'password'),
        'email' => env('HELPDESK_ADMIN_EMAIL', 'admin@example.com'),
        'firstname' => 'Helpdesk',
        'lastname' => 'Administrator',
    ],

    /*
    |--------------------------------------------------------------------------
    | Single Sign-On from iRIMS-V
    |--------------------------------------------------------------------------
    |
    | iRIMS-V's "Help Desk" link sends its signed-in user to /sso with a
    | one-time token signed with this secret. It must be the SAME value as
    | HELPDESK_SSO_SECRET in iRIMS-V's .env. Leave it empty to turn SSO off;
    | /sso then just shows the normal sign-in page.
    |
    */

    'sso' => [
        'secret' => env('HELPDESK_SSO_SECRET'),
    ],

];
