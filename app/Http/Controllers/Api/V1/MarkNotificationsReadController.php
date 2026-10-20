<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\MarkNotificationsRead;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MarkNotificationsReadController extends Controller
{
    /**
     * Mark the given `ids`, or every notification when there are none, as read.
     */
    public function __invoke(Request $request, MarkNotificationsRead $markNotificationsRead): Response
    {
        $markNotificationsRead->handle($request->user(), $request->all());

        return response()->noContent();
    }
}
