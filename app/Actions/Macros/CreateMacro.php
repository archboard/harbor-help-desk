<?php

namespace App\Actions\Macros;

use App\Data\Macros\CreateMacroData;
use App\Enums\MacroScope;
use App\Models\Macro;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelData\Optional;

class CreateMacro
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Macro
    {
        $data = CreateMacroData::validateAndCreate($input);

        $macro = new Macro([
            'workspace_id' => Workspace::current()?->getKey(),
            'user_id' => $data->scope === MacroScope::PERSONAL ? $actor->getKey() : null,
            'desk_id' => $data->deskId instanceof Optional ? null : $data->deskId,
            'name' => $data->name,
            'body' => $data->body instanceof Optional ? null : $data->body,
            'body_is_internal' => $data->bodyIsInternal instanceof Optional ? false : $data->bodyIsInternal,
            'actions' => $data->actions instanceof Optional ? [] : $data->actions,
            'created_by' => $actor->getKey(),
        ]);

        Gate::forUser($actor)->authorize('create', $macro);

        $macro->save();

        return $macro;
    }
}
