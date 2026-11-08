<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\MarkNotificationsRead;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;

class MarkNotificationReadController extends Controller
{
    #[ApiBody]
    public function __invoke(#[CurrentUser] User $user, string $notification, MarkNotificationsRead $markNotificationsRead): Response
    {
        $markNotificationsRead->handle($user, ['ids' => [$notification]]);

        return response()->noContent();
    }
}
