<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Workspaces\SetAway;
use App\Data\Workspaces\SetAwayData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SetAwayController extends Controller
{
    #[ApiBody(SetAwayData::class)]
    public function __invoke(Request $request, #[CurrentUser] User $user, SetAway $setAway): Response
    {
        $setAway->handle($user, $request->all());

        return response()->noContent();
    }
}
