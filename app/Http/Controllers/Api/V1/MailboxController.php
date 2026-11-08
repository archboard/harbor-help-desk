<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Mailboxes\CreateMailbox;
use App\Actions\Mailboxes\DeleteMailbox;
use App\Actions\Mailboxes\UpdateMailbox;
use App\Data\Mailboxes\CreateMailboxData;
use App\Data\Mailboxes\MailboxData;
use App\Data\Mailboxes\UpdateMailboxData;
use App\Http\Attributes\ApiBody;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MailboxOAuthController;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
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

    #[ApiBody(CreateMailboxData::class)]
    public function store(Request $request, #[CurrentUser] User $user, CreateMailbox $createMailbox): MailboxData
    {
        return MailboxData::from($createMailbox->handle($user, $request->all()));
    }

    public function show(Mailbox $mailbox): MailboxData
    {
        Gate::authorize('view', $mailbox);

        return MailboxData::from($mailbox);
    }

    #[ApiBody(UpdateMailboxData::class)]
    public function update(Request $request, #[CurrentUser] User $user, Mailbox $mailbox, UpdateMailbox $updateMailbox): MailboxData
    {
        return MailboxData::from($updateMailbox->handle($user, $mailbox, $request->all()));
    }

    public function destroy(#[CurrentUser] User $user, Mailbox $mailbox, DeleteMailbox $deleteMailbox): Response
    {
        $deleteMailbox->handle($user, $mailbox);

        return response()->noContent();
    }
}
