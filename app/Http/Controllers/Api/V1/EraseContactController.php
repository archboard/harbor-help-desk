<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Contacts\EraseContact;
use App\Data\Contacts\ContactData;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;

class EraseContactController extends Controller
{
    /**
     * Irreversibly erase the contact's personal data. Admins only; send the contact's current email as `confirm_email`.
     */
    public function __invoke(Request $request, #[CurrentUser] User $user, Contact $contact, EraseContact $eraseContact): ContactData
    {
        return ContactData::from($eraseContact->handle($user, $contact, $request->all()));
    }
}
