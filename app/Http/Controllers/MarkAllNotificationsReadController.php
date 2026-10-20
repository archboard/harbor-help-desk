<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\MarkNotificationsRead;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MarkAllNotificationsReadController extends Controller
{
    public function __invoke(Request $request, MarkNotificationsRead $markNotificationsRead): Response
    {
        $markNotificationsRead->handle($request->user());

        return response()->noContent();
    }
}
