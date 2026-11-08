<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Exports\RequestContactExport;
use App\Data\Exports\ExportData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;

class RequestContactExportController extends Controller
{
    /**
     * Queue an export of the contact's data. Admins only.
     */
    #[ApiBody]
    public function __invoke(#[CurrentUser] User $user, Contact $contact, RequestContactExport $requestContactExport): ExportData
    {
        return ExportData::from($requestContactExport->handle($user, $contact));
    }
}
