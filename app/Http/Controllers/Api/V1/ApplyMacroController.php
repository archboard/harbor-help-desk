<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Macros\ApplyMacro;
use App\Data\Macros\AppliedMacroData;
use App\Http\Controllers\Controller;
use App\Models\Macro;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplyMacroController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket, Macro $macro, ApplyMacro $applyMacro): JsonResponse
    {
        $applied = $applyMacro->handle($request->user(), $ticket, $macro);

        return response()->json(new AppliedMacroData($applied['body'], $applied['is_internal']));
    }
}
