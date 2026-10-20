<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AttachmentsController extends Controller
{
    /**
     * Show the attachment limits. The current values come from the shared `workspace.settings` prop.
     */
    public function edit(): Response
    {
        $workspace = Workspace::current();

        Gate::authorize('update', $workspace);

        return Inertia::render('workspace/Attachments', [
            'maxSizeKb' => $workspace->settings->effectiveAttachmentMaxKb(),
            'multitenant' => config('harbor.multitenant'),
            'platformBlockedExtensions' => config('harbor.attachments.blocked_extensions'),
        ]);
    }
}
