<?php

namespace App\Automations\Actions;

use App\Actions\Tickets\AddTicketComment;
use App\Automations\Context;
use App\Data\Automations\Actions\SendReplyConfigData;

/**
 * Sends a templated public reply, emailed to the requester, through {@see AddTicketComment}.
 * Guarded as described on {@see PostsComments}.
 */
class SendReply extends PostsComments
{
    public function key(): string
    {
        return 'send_reply';
    }

    public function label(): string
    {
        return __('Send a reply');
    }

    public function configData(): string
    {
        return SendReplyConfigData::class;
    }

    public function execute(Context $context, array $config): string
    {
        return $this->post($context, $this->render($context, SendReplyConfigData::from($config)->body, 65535), false);
    }

    public function describe(Context $context, array $config): string
    {
        return $this->replyBlockedReason($context)
            ?? __('Would send the reply: :body', ['body' => $this->render($context, SendReplyConfigData::from($config)->body, 200)]);
    }
}
