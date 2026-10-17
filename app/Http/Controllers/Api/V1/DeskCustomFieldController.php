<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Desks\SyncDeskCustomFields;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomFieldResource;
use App\Models\Desk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeskCustomFieldController extends Controller
{
    /**
     * Replace the desk's custom fields with `field_ids`.
     */
    public function update(Request $request, Desk $desk, SyncDeskCustomFields $syncDeskCustomFields): AnonymousResourceCollection
    {
        $syncDeskCustomFields->handle($request->user(), $desk, $request->all());

        return CustomFieldResource::collection($desk->customFields);
    }
}
