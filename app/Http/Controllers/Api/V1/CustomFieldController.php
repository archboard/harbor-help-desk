<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\DeleteCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Data\CustomFields\CreateCustomFieldData;
use App\Data\CustomFields\CustomFieldData;
use App\Data\CustomFields\UpdateCustomFieldData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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

    #[ApiBody(CreateCustomFieldData::class)]
    public function store(Request $request, #[CurrentUser] User $user, CreateCustomField $createCustomField): CustomFieldData
    {
        return CustomFieldData::from($createCustomField->handle($user, $request->all()));
    }

    #[ApiBody(UpdateCustomFieldData::class)]
    public function update(Request $request, #[CurrentUser] User $user, CustomField $customField, UpdateCustomField $updateCustomField): CustomFieldData
    {
        return CustomFieldData::from($updateCustomField->handle($user, $customField, $request->all()));
    }

    public function destroy(#[CurrentUser] User $user, CustomField $customField, DeleteCustomField $deleteCustomField): Response
    {
        $deleteCustomField->handle($user, $customField);

        return response()->noContent();
    }
}
