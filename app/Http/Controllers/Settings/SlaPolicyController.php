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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SlaPolicyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SlaPolicy::class);

        return Inertia::render('settings/SlaPolicies', [
            'policies' => SlaPolicyData::collect(SlaPolicy::query()->with('businessSchedule')->orderBy('name')->get()),
            'schedules' => BusinessScheduleData::collect(BusinessSchedule::query()->orderBy('name')->get()),
            'priorities' => TicketPriority::options(),
            'metrics' => SlaMetric::options(),
            'canManage' => Gate::forUser($request->user())->allows('create', SlaPolicy::class),
        ]);
    }

    public function store(Request $request, CreateSlaPolicy $createSlaPolicy): RedirectResponse
    {
        $createSlaPolicy->handle($request->user(), $request->all());

        Flash::success(__('SLA policy created.'));

        return back();
    }

    public function update(Request $request, SlaPolicy $slaPolicy, UpdateSlaPolicy $updateSlaPolicy): RedirectResponse
    {
        $updateSlaPolicy->handle($request->user(), $slaPolicy, $request->all());

        Flash::success(__('SLA policy updated.'));

        return back();
    }

    public function destroy(Request $request, SlaPolicy $slaPolicy, DeleteSlaPolicy $deleteSlaPolicy): RedirectResponse
    {
        $deleteSlaPolicy->handle($request->user(), $slaPolicy);

        Flash::success(__('SLA policy deleted.'));

        return back();
    }
}
