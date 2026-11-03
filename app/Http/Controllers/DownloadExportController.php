<?php

namespace App\Http\Controllers;

use App\Actions\Exports\DownloadExport;
use App\Models\Export;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadExportController extends Controller
{
    /**
     * The browser download. The route is signed, and the user must be an admin or the requester.
     */
    public function __invoke(#[CurrentUser] User $user, Export $export, DownloadExport $downloadExport): StreamedResponse
    {
        return $downloadExport->handle($user, $export);
    }
}
