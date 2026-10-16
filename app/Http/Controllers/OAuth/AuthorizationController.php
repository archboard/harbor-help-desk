<?php

namespace App\Http\Controllers\OAuth;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client;
use Laravel\Passport\Http\Controllers\AuthorizationController as PassportAuthorizationController;
use Laravel\Passport\Scope;

class AuthorizationController extends PassportAuthorizationController
{
    /**
     * Always show the consent screen, so every token gets the workspaces picked for it.
     *
     * @param  Scope[]  $scopes
     */
    protected function hasGrantedScopes(Authenticatable $user, Client $client, array $scopes): bool
    {
        return false;
    }
}
