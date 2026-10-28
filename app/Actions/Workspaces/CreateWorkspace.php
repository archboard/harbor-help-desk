<?php

namespace App\Actions\Workspaces;

use App\Data\Workspaces\CreateWorkspaceData;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    /**
     * Create a workspace with its default desk and hosted mailbox. Landlord code: the platform
     * API and the self-hosted install call it, with no actor.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): Workspace
    {
        $data = CreateWorkspaceData::validateAndCreate($input);

        return DB::transaction(fn (): Workspace => Workspace::create($data->filled()));
    }
}
