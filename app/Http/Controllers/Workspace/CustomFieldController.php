<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\CustomFields\CreateCustomField;
use App\Actions\CustomFields\DeleteCustomField;
use App\Actions\CustomFields\UpdateCustomField;
use App\Data\CustomFields\CustomFieldData;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldController extends Controller
{
    /**
     * Show the workspace's custom field definitions, for admins to manage.
     */
    public function index(): Response
    {
        Gate::authorize('create', CustomField::class);

        return Inertia::render('workspace/CustomFields', [
            'fields' => CustomFieldData::collect(CustomField::query()->with('desks')->orderBy('position')->orderBy('id')->get()),
            'types' => CustomFieldType::options(),
        ]);
    }

    public function store(Request $request, CreateCustomField $createCustomField): RedirectResponse
    {
        $createCustomField->handle($request->user(), $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field created.')]);

        return back();
    }

    public function update(Request $request, CustomField $customField, UpdateCustomField $updateCustomField): RedirectResponse
    {
        $updateCustomField->handle($request->user(), $customField, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field updated.')]);

        return back();
    }

    public function destroy(Request $request, CustomField $customField, DeleteCustomField $deleteCustomField): RedirectResponse
    {
        $deleteCustomField->handle($request->user(), $customField);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Custom field deleted.')]);

        return back();
    }
}
