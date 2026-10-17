<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\DeleteCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Http\Controllers\Controller;
use App\Http\Resources\CustomFieldResource;
use App\Models\CustomField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CustomFieldController extends Controller
{
    /**
     * Every custom field in the current workspace, in display order.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CustomField::class);

        return CustomFieldResource::collection(CustomField::query()->orderBy('position')->orderBy('id')->get());
    }

    public function store(Request $request, CreateCustomField $createCustomField): CustomFieldResource
    {
        return new CustomFieldResource($createCustomField->handle($request->user(), $request->all()));
    }

    public function update(Request $request, CustomField $customField, UpdateCustomField $updateCustomField): CustomFieldResource
    {
        return new CustomFieldResource($updateCustomField->handle($request->user(), $customField, $request->all()));
    }

    public function destroy(Request $request, CustomField $customField, DeleteCustomField $deleteCustomField): Response
    {
        $deleteCustomField->handle($request->user(), $customField);

        return response()->noContent();
    }
}
