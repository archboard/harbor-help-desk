<?php

namespace App\Http\Controllers;

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\CreateWorkspaceOwner;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InstallController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('install/Install');
    }

    /**
     * Create the owner, and self-hosted the workspace first, then sign the owner in.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $workspace = Workspace::current() ?? $this->createWorkspace($request);

            return app(CreateWorkspaceOwner::class)->handle($workspace, $request->only(['name', 'email', 'password', 'password_confirmation']));
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('setup_workspace_id');

        return to_route('tickets.index');
    }

    /**
     * The self-hosted workspace. Its fields are prefixed with `workspace_` in the form, apart
     * from the owner's.
     */
    private function createWorkspace(Request $request): Workspace
    {
        try {
            return app(CreateWorkspace::class)->handle(['name' => $request->input('workspace_name'), 'subdomain' => 'default']);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key): array => ["workspace_{$key}" => $messages])->all(),
            );
        }
    }
}
