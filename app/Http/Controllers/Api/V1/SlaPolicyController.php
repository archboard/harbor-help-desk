<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SlaPolicies\CreateSlaPolicy;
use App\Actions\SlaPolicies\DeleteSlaPolicy;
use App\Actions\SlaPolicies\UpdateSlaPolicy;
use App\Data\SlaPolicies\SlaPolicyData;
use App\Http\Controllers\Controller;
use App\Models\SlaPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class SlaPolicyController extends Controller
{
    /**
     * Every SLA policy in the current workspace.
     *
     * @return Collection<int, SlaPolicyData>
     */
    public function index(): Collection
    {
        Gate::authorize('viewAny', SlaPolicy::class);

        return SlaPolicyData::collect(SlaPolicy::query()->with('businessSchedule')->orderBy('name')->orderBy('id')->get());
    }

    public function store(Request $request, CreateSlaPolicy $createSlaPolicy): SlaPolicyData
    {
        return SlaPolicyData::from($createSlaPolicy->handle($request->user(), $request->all()));
    }

    public function show(SlaPolicy $slaPolicy): SlaPolicyData
    {
        Gate::authorize('view', $slaPolicy);

        return SlaPolicyData::from($slaPolicy);
    }

    public function update(Request $request, SlaPolicy $slaPolicy, UpdateSlaPolicy $updateSlaPolicy): SlaPolicyData
    {
        return SlaPolicyData::from($updateSlaPolicy->handle($request->user(), $slaPolicy, $request->all()));
    }

    public function destroy(Request $request, SlaPolicy $slaPolicy, DeleteSlaPolicy $deleteSlaPolicy): Response
    {
        $deleteSlaPolicy->handle($request->user(), $slaPolicy);

        return response()->noContent();
    }
}
