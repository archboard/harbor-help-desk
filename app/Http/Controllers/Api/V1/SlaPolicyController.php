<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SlaPolicies\CreateSlaPolicy;
use App\Actions\SlaPolicies\DeleteSlaPolicy;
use App\Actions\SlaPolicies\UpdateSlaPolicy;
use App\Data\SlaPolicies\CreateSlaPolicyData;
use App\Data\SlaPolicies\SlaPolicyData;
use App\Data\SlaPolicies\UpdateSlaPolicyData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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

    #[ApiBody(CreateSlaPolicyData::class)]
    public function store(Request $request, #[CurrentUser] User $user, CreateSlaPolicy $createSlaPolicy): SlaPolicyData
    {
        return SlaPolicyData::from($createSlaPolicy->handle($user, $request->all()));
    }

    public function show(SlaPolicy $slaPolicy): SlaPolicyData
    {
        Gate::authorize('view', $slaPolicy);

        return SlaPolicyData::from($slaPolicy);
    }

    #[ApiBody(UpdateSlaPolicyData::class)]
    public function update(Request $request, #[CurrentUser] User $user, SlaPolicy $slaPolicy, UpdateSlaPolicy $updateSlaPolicy): SlaPolicyData
    {
        return SlaPolicyData::from($updateSlaPolicy->handle($user, $slaPolicy, $request->all()));
    }

    public function destroy(#[CurrentUser] User $user, SlaPolicy $slaPolicy, DeleteSlaPolicy $deleteSlaPolicy): Response
    {
        $deleteSlaPolicy->handle($user, $slaPolicy);

        return response()->noContent();
    }
}
