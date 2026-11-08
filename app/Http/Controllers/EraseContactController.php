<?php

namespace App\Http\Controllers;

use App\Actions\Contacts\EraseContact;
use App\Http\Flash;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EraseContactController extends Controller
{
    public function __invoke(Request $request, #[CurrentUser] User $user, Contact $contact, EraseContact $eraseContact): RedirectResponse
    {
        $eraseContact->handle($user, $contact, $request->all());

        Flash::success(__('The contact has been erased.'));

        return back();
    }
}
