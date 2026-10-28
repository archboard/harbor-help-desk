<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\MarkNotificationsRead;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class MarkNotificationReadController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, string $notification, MarkNotificationsRead $markNotificationsRead): Response
    {
        $markNotificationsRead->handle($user, ['ids' => [$notification]]);

        return response()->noContent();
    }
}
