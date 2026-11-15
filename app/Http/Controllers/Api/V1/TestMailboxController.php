<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Mailboxes\TestMailbox;
use App\Data\Mailboxes\MailboxTestData;
use App\Http\Attributes\ApiBody;
use App\Http\Attributes\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

class TestMailboxController extends Controller
{
    /**
     * Send a test email to yourself and connect to the inbox now, and return how each went.
     * Nothing is saved.
     */
    #[ApiBody]
    #[ApiResponse(MailboxTestData::class)]
    public function __invoke(#[CurrentUser] User $user, Mailbox $mailbox, TestMailbox $testMailbox): JsonResponse
    {
        // Nothing is created, so not the 201 a POST of Data answers with.
        return response()->json($testMailbox->handle($user, $mailbox));
    }
}
