<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Mailboxes\CreateMailbox;
use App\Actions\Mailboxes\DeleteMailbox;
use App\Actions\Mailboxes\UpdateMailbox;
use App\Data\Desks\DeskData;
use App\Data\Mailboxes\MailboxData;
use App\Enums\MailboxDriver;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MailboxOAuthController;
use App\Http\Flash;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The workspace's mailboxes. IMAP/SMTP ones are added here; Google and Microsoft ones are
 * connected through {@see MailboxOAuthController}.
 */
class EmailController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Mailbox::class);

        return Inertia::render('workspace/Email', [
            'mailboxes' => MailboxData::collect(Mailbox::query()->oldest('id')->get()),
            'desks' => DeskData::collect(Desk::query()->orderBy('name')->get()),
            'oauthProviders' => [
                'google' => filled(config('services.google_mail.client_id')),
                'microsoft' => filled(config('services.microsoft_mail.client_id')),
            ],
        ]);
    }

    public function store(Request $request, #[CurrentUser] User $user, CreateMailbox $createMailbox): RedirectResponse
    {
        $createMailbox->handle($user, [...$request->all(), 'driver' => MailboxDriver::IMAP->value]);

        Flash::success(__('Mailbox added.'));

        return back();
    }

    public function update(Request $request, #[CurrentUser] User $user, Mailbox $mailbox, UpdateMailbox $updateMailbox): RedirectResponse
    {
        $updateMailbox->handle($user, $mailbox, $request->all());

        Flash::success(__('Mailbox updated.'));

        return back();
    }

    public function destroy(#[CurrentUser] User $user, Mailbox $mailbox, DeleteMailbox $deleteMailbox): RedirectResponse
    {
        $deleteMailbox->handle($user, $mailbox);

        Flash::success(__('Mailbox deleted.'));

        return back();
    }
}
