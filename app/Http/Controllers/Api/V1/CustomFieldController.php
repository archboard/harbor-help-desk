<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\DeleteCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Data\CustomFields\CustomFieldData;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class CustomFieldController extends Controller
{
    /**
     * Every custom field in the current workspace, in display order.
     *
     * @return Collection<int, CustomFieldData>
     */
    public function index(): Collection
    {
        Gate::authorize('viewAny', CustomField::class);

        return CustomFieldData::collect(CustomField::query()->orderBy('position')->orderBy('id')->get());
    }

    public function store(Request $request, CreateCustomField $createCustomField): CustomFieldData
    {
        return CustomFieldData::from($createCustomField->handle($request->user(), $request->all()));
    }

    public function update(Request $request, CustomField $customField, UpdateCustomField $updateCustomField): CustomFieldData
    {
        return CustomFieldData::from($updateCustomField->handle($request->user(), $customField, $request->all()));
    }

    public function destroy(Request $request, CustomField $customField, DeleteCustomField $deleteCustomField): Response
    {
        $deleteCustomField->handle($request->user(), $customField);

        return response()->noContent();
    }
}
