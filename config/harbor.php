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

];
