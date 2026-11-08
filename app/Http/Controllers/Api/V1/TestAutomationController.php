<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Automations\TestAutomation;
use App\Data\Automations\TestAutomationData;
use App\Http\Attributes\ApiBody;
use App\Http\Attributes\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestAutomationController extends Controller
{
    /**
     * Dry-run an unsaved definition against a ticket. Nothing is changed.
     */
    #[ApiBody(TestAutomationData::class)]
    #[ApiResponse(schema: ['type' => 'object'])]
    public function __invoke(Request $request, #[CurrentUser] User $user, TestAutomation $testAutomation): JsonResponse
    {
        // Nothing is created, so not the 201 a POST of Data answers with.
        return response()->json($testAutomation->handle($user, $request->all()));
    }
}
