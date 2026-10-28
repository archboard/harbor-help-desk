<?php

namespace App\Http\Controllers\Settings;

use App\Actions\SlaPolicies\CreateSlaPolicy;
use App\Actions\SlaPolicies\DeleteSlaPolicy;
use App\Actions\SlaPolicies\UpdateSlaPolicy;
use App\Data\BusinessSchedules\BusinessScheduleData;
use App\Data\SlaPolicies\SlaPolicyData;
use App\Enums\SlaMetric;
use App\Enums\TicketPriority;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\BusinessSchedule;
use App\Models\SlaPolicy;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SlaPolicyController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        Gate::authorize('viewAny', SlaPolicy::class);

        return Inertia::render('settings/SlaPolicies', [
            'policies' => SlaPolicyData::collect(SlaPolicy::query()->with('businessSchedule')->orderBy('name')->get()),
            'schedules' => BusinessScheduleData::collect(BusinessSchedule::query()->orderBy('name')->get()),
            'priorities' => TicketPriority::options(),
            'metrics' => SlaMetric::options(),
            'canManage' => Gate::forUser($user)->allows('create', SlaPolicy::class),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, CreateSlaPolicy $createSlaPolicy): RedirectResponse
    {
        $createSlaPolicy->handle($user, $request->all());

        Flash::success(__('SLA policy created.'));

        return back();
    }

    public function update(Request $request, #[CurrentUser] User $user, SlaPolicy $slaPolicy, UpdateSlaPolicy $updateSlaPolicy): RedirectResponse
    {
        $updateSlaPolicy->handle($user, $slaPolicy, $request->all());

        Flash::success(__('SLA policy updated.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, SlaPolicy $slaPolicy, DeleteSlaPolicy $deleteSlaPolicy): RedirectResponse
    {
        $deleteSlaPolicy->handle($user, $slaPolicy);

        Flash::success(__('SLA policy deleted.'));

        return back();
    }
}
