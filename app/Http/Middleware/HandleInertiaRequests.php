<?php

namespace App\Http\Middleware;

use App\Data\Realtime\RealtimeData;
use App\Data\Users\CurrentUserData;
use App\Data\Workspaces\WorkspaceData;
use App\Models\Workspace;
use Illuminate\Http\Request;
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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user() === null ? null : CurrentUserData::from($request->user()),
            ],
            // Lazy, because the workspace is resolved by route middleware that runs after this one.
            'workspace' => fn (): ?WorkspaceData => Workspace::current() === null ? null : WorkspaceData::from(Workspace::current()),
            // Lazy for the same reason. Authenticated pages with a workspace only.
            ...($request->user() === null ? [] : ['notifications' => fn (): ?array => Workspace::current() === null ? null : [
                'unread_count' => $request->user()->notifications()->reorder()->whereNull('read_at')->count(),
            ]]),
            // Authenticated pages only, so guests never open a socket.
            ...($request->user() === null ? [] : ['realtime' => fn (): RealtimeData => RealtimeData::fromConfig()]),
        ];
    }
}
