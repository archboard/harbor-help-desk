<?php

namespace App\Http\Middleware;

use App\Data\Contacts\ContactData;
use App\Data\Realtime\RealtimeData;
use App\Data\Users\CurrentUserData;
use App\Data\Workspaces\WorkspaceData;
use App\Http\TicketPageProps;
use App\Models\Automation;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // The portal's default guard is the contact guard: no agent is shared there.
        $user = $request->routeIs('portal.*') ? null : $request->user('web');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'multitenant' => (bool) config('harbor.multitenant'),
            'auth' => [
                'user' => $user === null ? null : CurrentUserData::from($user),
                // Portal pages only.
                ...($request->routeIs('portal.*') ? ['contact' => Auth::guard('contact')->user() === null ? null : ContactData::from(Auth::guard('contact')->user())] : []),
            ],
            // Lazy like the workspace. Guest pages only: whether to link to the customer portal.
            ...($user === null ? ['portalEnabled' => fn (): bool => Workspace::current()?->settings->portalEnabled ?? false] : []),
            // Lazy, because the workspace is resolved by route middleware that runs after this one.
            'workspace' => fn (): ?WorkspaceData => Workspace::current() === null ? null : WorkspaceData::from(Workspace::current()),
            // Lazy for the same reason. Authenticated pages with a workspace only.
            ...($user === null ? [] : ['notifications' => fn (): ?array => Workspace::current() === null ? null : [
                'unread_count' => $user->notifications()->reorder()->whereNull('read_at')->count(),
            ]]),
            // Lazy like the workspace. Whether to show the Automations link.
            ...($user === null ? [] : ['canViewAutomations' => fn (): bool => Workspace::current() !== null && $user->can('viewAny', Automation::class)]),
            // The quick view's ticket, for `?preview={id}`. A closure so a mutation that redirects back refreshes it.
            ...($user === null ? [] : ['preview' => function () use ($request, $user): ?array {
                $id = $request->string('preview')->toString();

                if ($id === '') {
                    return null;
                }

                $ticket = Ticket::withTrashed()->findOrFail($id);
                Gate::authorize('view', $ticket);

                return TicketPageProps::handle($user, $ticket);
            }]),
            // Authenticated pages only, so guests never open a socket.
            ...($user === null ? [] : ['realtime' => fn (): RealtimeData => RealtimeData::fromConfig()]),
        ];
    }
}
