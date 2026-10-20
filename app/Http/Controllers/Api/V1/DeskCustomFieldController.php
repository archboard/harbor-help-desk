<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Desks\SyncDeskCustomFields;
use App\Data\CustomFields\CustomFieldData;
use App\Http\Controllers\Controller;
use App\Models\Desk;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DeskCustomFieldController extends Controller
{
    /**
     * Replace the desk's custom fields with `field_ids`.
     *
     * @return Collection<int, CustomFieldData>
     */
    public function update(Request $request, Desk $desk, SyncDeskCustomFields $syncDeskCustomFields): Collection
    {
        $syncDeskCustomFields->handle($request->user(), $desk, $request->all());

        return CustomFieldData::collect($desk->customFields);
    }
}
