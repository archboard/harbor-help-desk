<?php

namespace App\Http\Controllers;

use App\Actions\Automations\ReorderAutomations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReorderAutomationsController extends Controller
{
    public function __invoke(Request $request, ReorderAutomations $reorderAutomations): RedirectResponse
    {
        $reorderAutomations->handle($request->user(), $request->all());

        return back();
    }
}
