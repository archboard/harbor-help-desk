<?php

namespace App\Http\Controllers;

use App\Actions\Exports\RequestContactExport;
use App\Http\Flash;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class RequestContactExportController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, Contact $contact, RequestContactExport $requestContactExport): RedirectResponse
    {
        $requestContactExport->handle($user, $contact);

        Flash::success(__('The export is being prepared. You will get an email when it is ready.'));

        return back();
    }
}
