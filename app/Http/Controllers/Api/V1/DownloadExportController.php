<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Exports\DownloadExport;
use App\Http\Controllers\Controller;
use App\Models\Export;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadExportController extends Controller
{
    /**
     * The export's zip. The token authenticates it, so the link needs no signature.
     */
    public function __invoke(#[CurrentUser] User $user, Export $export, DownloadExport $downloadExport): StreamedResponse
    {
        return $downloadExport->handle($user, $export);
    }
}
