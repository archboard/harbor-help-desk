<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Mailboxes\CreateMailbox;
use App\Actions\Mailboxes\DeleteMailbox;
use App\Actions\Mailboxes\UpdateMailbox;
use App\Data\Desks\DeskData;
use App\Enums\MailboxDriver;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MailboxOAuthController;
use App\Http\Resources\MailboxResource;
use App\Models\Desk;
use App\Models\Mailbox;
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

        return Inertia::render('settings/Email', [
            'mailboxes' => MailboxResource::collection(Mailbox::query()->oldest('id')->get()),
            'desks' => DeskData::collect(Desk::query()->orderBy('name')->get()),
            'oauthProviders' => [
                'google' => filled(config('services.google_mail.client_id')),
                'microsoft' => filled(config('services.microsoft_mail.client_id')),
            ],
        ]);
    }

    public function store(Request $request, CreateMailbox $createMailbox): RedirectResponse
    {
        $createMailbox->handle($request->user(), [...$request->all(), 'driver' => MailboxDriver::IMAP->value]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailbox added.')]);

        return back();
    }

    public function update(Request $request, Mailbox $mailbox, UpdateMailbox $updateMailbox): RedirectResponse
    {
        $updateMailbox->handle($request->user(), $mailbox, $request->all());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailbox updated.')]);

        return back();
    }

    public function destroy(Request $request, Mailbox $mailbox, DeleteMailbox $deleteMailbox): RedirectResponse
    {
        $deleteMailbox->handle($request->user(), $mailbox);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mailbox deleted.')]);

        return back();
    }
}
