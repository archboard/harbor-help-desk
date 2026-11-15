<?php

namespace App\Http\Controllers\Workspace;

use App\Actions\Mailboxes\TestMailbox;
use App\Data\Mailboxes\MailboxCheckData;
use App\Http\Controllers\Controller;
use App\Http\Flash;
use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class TestMailboxController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, Mailbox $mailbox, TestMailbox $testMailbox): RedirectResponse
    {
        $result = $testMailbox->handle($user, $mailbox);
        $checks = array_filter([
            __('Sending') => $result->sending,
            __('Receiving') => $result->receiving,
        ]);
        $message = collect($checks)->map(fn (MailboxCheckData $check, string $side): string => "{$side}: {$check->message}")->join(' ');

        if (collect($checks)->every(fn (MailboxCheckData $check): bool => $check->passed)) {
            Flash::success($message);
        } else {
            Flash::error($message);
        }

        return back();
    }
}
