<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\SetAway;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SetAwayController extends Controller
{
    public function __invoke(Request $request, SetAway $setAway): Response
    {
        $setAway->handle($request->user(), $request->all());

        return response()->noContent();
    }
}
