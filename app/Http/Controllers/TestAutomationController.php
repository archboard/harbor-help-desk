<?php

namespace App\Http\Controllers;

use App\Actions\Automations\TestAutomation;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestAutomationController extends Controller
{
    /**
     * Dry-run an unsaved definition against a ticket. Nothing is changed.
     */
    public function __invoke(Request $request, #[CurrentUser] User $user, TestAutomation $testAutomation): JsonResponse
    {
        // Nothing is created, so not the 201 a POST of Data answers with.
        return response()->json($testAutomation->handle($user, $request->all()));
    }
}
