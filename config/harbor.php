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

];
