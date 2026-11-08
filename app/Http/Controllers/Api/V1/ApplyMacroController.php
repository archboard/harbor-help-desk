<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Macros\ApplyMacro;
use App\Data\Macros\AppliedMacroData;
use App\Http\Attributes\ApiBody;
use App\Http\Attributes\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Macro;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class ApplyMacroController extends Controller
{
    #[ApiBody]
    #[ApiResponse(AppliedMacroData::class)]
    public function __invoke(#[CurrentUser] User $user, Ticket $ticket, Macro $macro, ApplyMacro $applyMacro): JsonResponse
    {
        $applied = $applyMacro->handle($user, $ticket, $macro);

        return response()->json(new AppliedMacroData($applied['body'], $applied['is_internal']));
    }
}
