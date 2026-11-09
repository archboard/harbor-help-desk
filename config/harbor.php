<?php

return [

    'version' => env('HARBOR_VERSION', 'dev'),

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

    // Multitenant only: the bearer token the platform (billing, sign-up) sends to create and
    // delete workspaces at /api/platform/workspaces. Unset, that API is off.
    'platform_token' => env('HARBOR_PLATFORM_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon
    |--------------------------------------------------------------------------
    |
    | In multitenant mode only these platform operators (comma-separated
    | emails) may open the Horizon dashboard. Self-hosted, the workspace's
    | owners and admins may.
    |
    */

    'horizon_emails' => array_values(array_filter(array_map(
        fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('HARBOR_HORIZON_EMAILS', '')),
    ))),

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
        // Platform cap: a workspace's own limit can't exceed it.
        'max_size_kb' => (int) env('HARBOR_ATTACHMENTS_MAX_SIZE_KB', 25 * 1024),
        // Always blocked in the cloud, on top of each workspace's own list; self-hosted
        // workspaces start from this list and edit it freely.
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
        // The mailer for app email that has no workspace mailbox to go through (outside a
        // workspace, or a workspace with no mailbox), and for the hosted mailbox's replies.
        'platform_mailer' => env('HARBOR_PLATFORM_MAILER', 'log'),

        // Lets Gmail and Microsoft Graph push new-mail notifications to Harbor: the token on the
        // Gmail Pub/Sub push URL. Needs an https APP_URL; mailboxes poll or watch without it.
        'push_secret' => env('HARBOR_MAIL_PUSH_SECRET'),

        // With `mailboxes:watch` running, connected mailboxes are checked every few seconds over
        // held-open connections, and the per-minute poll only runs as a 5-minute backstop.
        'watch' => (bool) env('HARBOR_MAIL_WATCH', false),
        'watch_interval' => (int) env('HARBOR_MAIL_WATCH_INTERVAL', 10),
    ],

];
