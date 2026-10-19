<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Multi-tenancy
    |--------------------------------------------------------------------------
    |
    | When true (cloud), the workspace is found by the request's subdomain of
    | the root domain below. When false (self-hosted), the single workspace
    | is always the current one.
    |
    */

    'multitenant' => (bool) env('HARBOR_MULTITENANT', true),

    'domain' => env('HARBOR_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | Ticket files are stored on this filesystem disk (local by default, or
    | e.g. "s3"). Uploads over the size limit or with a blocked extension
    | are rejected.
    |
    */

    'attachments' => [
        'disk' => env('HARBOR_ATTACHMENTS_DISK', 'local'),
        'max_size_kb' => (int) env('HARBOR_ATTACHMENTS_MAX_SIZE_KB', 25 * 1024),
        'blocked_extensions' => ['bat', 'cmd', 'com', 'cpl', 'dll', 'exe', 'hta', 'jar', 'js', 'jse', 'lnk', 'msi', 'msp', 'ps1', 'scr', 'sh', 'vbe', 'vbs', 'wsf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Email
    |--------------------------------------------------------------------------
    |
    | With an inbound domain set, every workspace gets a hosted mailbox on it
    | that receives mail through Postmark's inbound webhook. Leave it null
    | (self-hosted) to use only connected IMAP/SMTP or OAuth mailboxes.
    |
    */

    'mail' => [
        'inbound_domain' => env('HARBOR_INBOUND_DOMAIN'),
        'postmark_webhook_secret' => env('HARBOR_POSTMARK_WEBHOOK_SECRET'),
        'new_tickets_per_sender_per_hour' => (int) env('HARBOR_NEW_TICKETS_PER_SENDER_PER_HOUR', 20),

        // Lets Gmail and Microsoft Graph push new-mail notifications to Harbor: the token on the
        // Gmail Pub/Sub push URL. Needs an https APP_URL; mailboxes poll or watch without it.
        'push_secret' => env('HARBOR_MAIL_PUSH_SECRET'),

        // With `mailboxes:watch` running, connected mailboxes are checked every few seconds over
        // held-open connections, and the per-minute poll only runs as a 5-minute backstop.
        'watch' => (bool) env('HARBOR_MAIL_WATCH', false),
        'watch_interval' => (int) env('HARBOR_MAIL_WATCH_INTERVAL', 10),
    ],

];
