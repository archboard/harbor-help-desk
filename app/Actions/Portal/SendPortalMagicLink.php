<?php

namespace App\Actions\Portal;

use App\Actions\Auth\SendMagicLink;
use App\Data\Portal\SendPortalMagicLinkData;
use App\Enums\PortalAccess;
use App\Models\Contact;
use App\Models\Workspace;
use App\Notifications\MagicLinkNotification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SendPortalMagicLink
{
    /**
     * Email a one-time portal sign-in link. Under `CONTACTS` access, addresses that aren't a contact get nothing, silently,
     * so contacts can't be enumerated. The link signs the email, not a contact: the contact is found or created on click.
     *
     * @param  array<string, mixed>  $input
     */
    public function handle(array $input): void
    {
        $email = SendPortalMagicLinkData::validateAndCreate($input)->email;

        $contact = Contact::query()->whereRaw('lower(email) = lower(?)', [$email])->first();

        if ($contact === null && Workspace::current()->settings->portalAccess === PortalAccess::CONTACTS) {
            return;
        }

        // Built during the request so the link keeps the current workspace host, which the signature covers.
        $url = URL::temporarySignedRoute('portal.login.show', now()->addMinutes(SendMagicLink::EXPIRES_IN_MINUTES), [
            'email' => $email,
            'token' => Str::random(40),
        ]);

        // An unsaved contact is just the mail route for an address that becomes a contact on click.
        ($contact ?? new Contact(['email' => $email]))->notify(new MagicLinkNotification($url));
    }
}
