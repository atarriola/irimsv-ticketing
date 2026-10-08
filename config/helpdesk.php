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
    | Email Notifications
    |--------------------------------------------------------------------------
    |
    | Whether ticket notifications are emailed as well as shown in the app.
    | Off until a mailer is configured in MAIL_* and a queue worker runs, so
    | that nothing is queued that could never be sent. People can still turn
    | their own emails off on the account page once this is on.
    |
    */

    'email_notifications' => (bool) env('HELPDESK_EMAIL_NOTIFICATIONS', false),

    /*
    |--------------------------------------------------------------------------
    | Ticket Lifecycle
    |--------------------------------------------------------------------------
    |
    | After a ticket is resolved its requester may confirm the fix or reopen the
    | ticket for `reopen_window_days`. A ticket still resolved after
    | `auto_close_days` is closed by the daily `tickets:close-resolved` run.
    | Read notifications older than `prune_notifications_after_days` are
    | removed by the daily `notifications:prune` run.
    |
    */

    'reopen_window_days' => (int) env('HELPDESK_REOPEN_WINDOW_DAYS', 14),
    'auto_close_days' => (int) env('HELPDESK_AUTO_CLOSE_DAYS', 7),
    'prune_notifications_after_days' => (int) env('HELPDESK_PRUNE_NOTIFICATIONS_AFTER_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Maintenance Notices
    |--------------------------------------------------------------------------
    |
    | A published news post of the "maintenance" kind is shown as a banner on
    | the dashboard and the ticket form for this many days after it goes out,
    | so people know about an outage before they report it.
    |
    */

    'maintenance_notice_days' => (int) env('HELPDESK_MAINTENANCE_NOTICE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Single Sign-On from iRIMS-V
    |--------------------------------------------------------------------------
    |
    | iRIMS-V's "Support Center" link sends its signed-in user to /sso with a
    | one-time token signed with this secret. It must be the SAME value as
    | HELPDESK_SSO_SECRET in iRIMS-V's .env. Leave it empty to turn SSO off;
    | /sso then just shows the normal sign-in page. A token is also refused
    | when its expiry lies more than `max_lifetime` seconds ahead, however
    | it was signed: iRIMS-V issues them for about a minute.
    |
    */

    'sso' => [
        'secret' => env('HELPDESK_SSO_SECRET'),
        'max_lifetime' => (int) env('HELPDESK_SSO_MAX_LIFETIME', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | "enforce" sends the policy, "report" only asks browsers to report what it
    | would have blocked (to the console), and "off" sends none. Scripts are
    | limited to this app's own, images to this app and the LRMIS photo host,
    | and connections to this app, the Vite dev server and the Reverb socket.
    |
    */

    'csp' => [
        'mode' => env('HELPDESK_CSP', 'enforce'),
        'reverb_host' => env('VITE_REVERB_HOST', env('REVERB_HOST')),
        'reverb_port' => env('VITE_REVERB_PORT', env('REVERB_PORT')),
        'reverb_scheme' => env('VITE_REVERB_SCHEME', env('REVERB_SCHEME', 'http')),
        'photo_url' => env('LRMIS_URL'),
    ],

];
