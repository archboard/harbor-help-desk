<?php

namespace App\Http\Controllers\Settings;

use App\Actions\BusinessSchedules\CreateBusinessSchedule;
use App\Actions\BusinessSchedules\DeleteBusinessSchedule;
use App\Actions\BusinessSchedules\UpdateBusinessSchedule;
use App\Data\BusinessSchedules\BusinessScheduleData;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\BusinessSchedule;
use App\Models\User;
use DateTimeZone;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BusinessScheduleController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        Gate::authorize('viewAny', BusinessSchedule::class);

        return Inertia::render('settings/BusinessHours', [
            'schedules' => BusinessScheduleData::collect(BusinessSchedule::query()->orderBy('name')->get()),
            'timezones' => DateTimeZone::listIdentifiers(),
            'canManage' => Gate::forUser($user)->allows('create', BusinessSchedule::class),
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, CreateBusinessSchedule $createBusinessSchedule): RedirectResponse
    {
        $createBusinessSchedule->handle($user, $request->all());

        Flash::success(__('Business hours created.'));

        return back();
    }

    public function update(Request $request, #[CurrentUser] User $user, BusinessSchedule $businessSchedule, UpdateBusinessSchedule $updateBusinessSchedule): RedirectResponse
    {
        $updateBusinessSchedule->handle($user, $businessSchedule, $request->all());

        Flash::success(__('Business hours updated.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, BusinessSchedule $businessSchedule, DeleteBusinessSchedule $deleteBusinessSchedule): RedirectResponse
    {
        $deleteBusinessSchedule->handle($user, $businessSchedule);

        Flash::success(__('Business hours deleted.'));

        return back();
    }
}
