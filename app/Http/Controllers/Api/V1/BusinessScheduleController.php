<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\BusinessSchedules\CreateBusinessSchedule;
use App\Actions\BusinessSchedules\DeleteBusinessSchedule;
use App\Actions\BusinessSchedules\UpdateBusinessSchedule;
use App\Data\BusinessSchedules\BusinessScheduleData;
use App\Http\Controllers\Controller;
use App\Models\BusinessSchedule;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class BusinessScheduleController extends Controller
{
    /**
     * Every business schedule in the current workspace.
     *
     * @return Collection<int, BusinessScheduleData>
     */
    public function index(): Collection
    {
        Gate::authorize('viewAny', BusinessSchedule::class);

        return BusinessScheduleData::collect(BusinessSchedule::query()->orderBy('name')->orderBy('id')->get());
    }

    public function store(Request $request, #[CurrentUser] User $user, CreateBusinessSchedule $createBusinessSchedule): BusinessScheduleData
    {
        return BusinessScheduleData::from($createBusinessSchedule->handle($user, $request->all()));
    }

    public function show(BusinessSchedule $businessSchedule): BusinessScheduleData
    {
        Gate::authorize('view', $businessSchedule);

        return BusinessScheduleData::from($businessSchedule);
    }

    public function update(Request $request, #[CurrentUser] User $user, BusinessSchedule $businessSchedule, UpdateBusinessSchedule $updateBusinessSchedule): BusinessScheduleData
    {
        return BusinessScheduleData::from($updateBusinessSchedule->handle($user, $businessSchedule, $request->all()));
    }

    public function destroy(#[CurrentUser] User $user, BusinessSchedule $businessSchedule, DeleteBusinessSchedule $deleteBusinessSchedule): Response
    {
        $deleteBusinessSchedule->handle($user, $businessSchedule);

        return response()->noContent();
    }
}
