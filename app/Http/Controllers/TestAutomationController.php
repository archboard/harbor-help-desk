<?php

namespace App\Http\Controllers;

use App\Actions\Automations\TestAutomation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestAutomationController extends Controller
{
    /**
     * Dry-run an unsaved definition against a ticket. Nothing is changed.
     */
    public function __invoke(Request $request, TestAutomation $testAutomation): JsonResponse
    {
        // Nothing is created, so not the 201 a POST of Data answers with.
        return response()->json($testAutomation->handle($request->user(), $request->all()));
    }
}
