<?php

namespace App\Data\Tickets;

use App\Actions\Tickets\AddTicketAttachment;
use App\Actions\Tickets\AddTicketComment;
use App\Data\InputData;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Optional;

/**
 * Who may set `is_internal`, `message_id`, `mentioned_user_ids` and `last_seen_comment_id` is checked by {@see AddTicketComment}.
 */
class AddTicketCommentData extends InputData
{
    /**
     * @param  bool|Optional  $isInternal  Whether the comment is only visible to agents.
     * @param  list<UploadedFile>|Optional  $attachments  Files to attach to the comment.
     * @param  list<string>|Optional  $mentionedUserIds  Ids of the agents to notify.
     */
    public function __construct(
        public string $body,
        public bool|Optional $isInternal,
        public string|Optional|null $messageId,
        public array|Optional $attachments,
        public array|Optional $mentionedUserIds,
        public string|Optional|null $lastSeenCommentId,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:65535'],
            'message_id' => ['sometimes', 'nullable', 'string', 'max:998'],
            'last_seen_comment_id' => ['sometimes', 'nullable', 'string'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'attachments.*' => AddTicketAttachment::rules(),
            'mentioned_user_ids' => ['sometimes', 'array'],
            'mentioned_user_ids.*' => ['string', 'distinct'],
        ];
    }
}
