<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Mailboxes\CreateMailbox;
use App\Actions\Mailboxes\DeleteMailbox;
use App\Actions\Mailboxes\UpdateMailbox;
use App\Data\Mailboxes\MailboxData;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MailboxOAuthController;
use App\Models\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Google and Microsoft mailboxes are connected in the browser, through
 * {@see MailboxOAuthController}.
 */
class MailboxController extends Controller
{
    /**
     * @return Collection<int, MailboxData>
     */
    public function index(): Collection
    {
        Gate::authorize('viewAny', Mailbox::class);

        return MailboxData::collect(Mailbox::query()->oldest('id')->get());
    }

    public function store(Request $request, CreateMailbox $createMailbox): MailboxData
    {
        return MailboxData::from($createMailbox->handle($request->user(), $request->all()));
    }

    public function show(Mailbox $mailbox): MailboxData
    {
        Gate::authorize('view', $mailbox);

        return MailboxData::from($mailbox);
    }

    public function update(Request $request, Mailbox $mailbox, UpdateMailbox $updateMailbox): MailboxData
    {
        return MailboxData::from($updateMailbox->handle($request->user(), $mailbox, $request->all()));
    }

    public function destroy(Request $request, Mailbox $mailbox, DeleteMailbox $deleteMailbox): Response
    {
        $deleteMailbox->handle($request->user(), $mailbox);

        return response()->noContent();
    }
}
