<?php

namespace App\Actions\Workspaces;

use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

class DeleteWorkspace
{
    /**
     * Delete the workspace, its attachment files and, through cascading foreign keys, all of
     * its rows. Its agents keep their accounts, since users are global.
     */
    public function handle(Workspace $workspace): void
    {
        $workspace->delete();

        Storage::disk(config('harbor.attachments.disk'))->deleteDirectory($workspace->getKey());
    }
}
