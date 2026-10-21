<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\SetAway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SetAwayController extends Controller
{
    public function __invoke(Request $request, SetAway $setAway): RedirectResponse
    {
        $setAway->handle($request->user(), $request->all());

        return back();
    }
}
