<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Exports\ListExports;
use App\Data\Exports\ExportData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Spatie\LaravelData\PaginatedDataCollection;

class ExportController extends Controller
{
    /**
     * The workspace's data exports, newest first. Admins only.
     *
     * @return PaginatedDataCollection<int, ExportData>
     */
    public function index(#[CurrentUser] User $user, ListExports $listExports): PaginatedDataCollection
    {
        return ExportData::collect($listExports->handle($user), PaginatedDataCollection::class);
    }
}
