<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Proxies whose X-Forwarded-* headers are honoured: "*" trusts the calling
    | proxy, or a comma-separated list of IPs/CIDRs. Leave unset when the app
    | is reached directly.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
