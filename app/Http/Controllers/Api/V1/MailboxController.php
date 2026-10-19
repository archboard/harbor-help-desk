<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Mailboxes\CreateMailbox;
use App\Actions\Mailboxes\DeleteMailbox;
use App\Actions\Mailboxes\UpdateMailbox;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MailboxOAuthController;
use App\Http\Resources\MailboxResource;
use App\Models\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Google and Microsoft mailboxes are connected in the browser, through
 * {@see MailboxOAuthController}.
 */
class MailboxController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Mailbox::class);

        return MailboxResource::collection(Mailbox::query()->oldest('id')->get());
    }

    public function store(Request $request, CreateMailbox $createMailbox): MailboxResource
    {
        return new MailboxResource($createMailbox->handle($request->user(), $request->all()));
    }

    public function show(Mailbox $mailbox): MailboxResource
    {
        Gate::authorize('view', $mailbox);

        return new MailboxResource($mailbox);
    }

    public function update(Request $request, Mailbox $mailbox, UpdateMailbox $updateMailbox): MailboxResource
    {
        return new MailboxResource($updateMailbox->handle($request->user(), $mailbox, $request->all()));
    }

    public function destroy(Request $request, Mailbox $mailbox, DeleteMailbox $deleteMailbox): Response
    {
        $deleteMailbox->handle($request->user(), $mailbox);

        return response()->noContent();
    }
}
