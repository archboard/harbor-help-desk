<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\MarkNotificationsRead;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MarkNotificationReadController extends Controller
{
    public function __invoke(Request $request, string $notification, MarkNotificationsRead $markNotificationsRead): Response
    {
        $markNotificationsRead->handle($request->user(), ['ids' => [$notification]]);

        return response()->noContent();
    }
}
