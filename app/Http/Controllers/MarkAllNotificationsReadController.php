<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\MarkNotificationsRead;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class MarkAllNotificationsReadController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, MarkNotificationsRead $markNotificationsRead): Response
    {
        $markNotificationsRead->handle($user);

        return response()->noContent();
    }
}
