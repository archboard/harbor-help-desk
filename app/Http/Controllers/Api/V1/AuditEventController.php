<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Audit\ListAuditEvents;
use App\Data\Audit\AuditEventData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Spatie\LaravelData\CursorPaginatedDataCollection;

class AuditEventController extends Controller
{
    /**
     * The workspace's admin activity, newest first. Admins only.
     *
     * @return CursorPaginatedDataCollection<int, AuditEventData>
     */
    public function index(Request $request, #[CurrentUser] User $user, ListAuditEvents $listAuditEvents): CursorPaginatedDataCollection
    {
        return AuditEventData::collect($listAuditEvents->handle($user, $request->query()), CursorPaginatedDataCollection::class);
    }
}
