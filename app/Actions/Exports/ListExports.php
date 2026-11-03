<?php

namespace App\Actions\Exports;

use App\Models\Export;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ListExports
{
    /**
     * The workspace's exports, newest first.
     *
     * @return LengthAwarePaginator<int, Export>
     */
    public function handle(User $actor): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Export::class);

        return Export::query()->with(['requester', 'contact'])->orderByDesc('id')->paginate(25);
    }
}
