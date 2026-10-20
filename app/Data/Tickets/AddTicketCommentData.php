<?php

namespace App\Data\Tickets;

use App\Actions\Tickets\AddTicketAttachment;
use App\Actions\Tickets\AddTicketComment;
use App\Data\InputData;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Optional;

/**
 * Who may set `is_internal`, `message_id` and `mentioned_user_ids` is checked by {@see AddTicketComment}.
 */
class AddTicketCommentData extends InputData
{
    /**
     * @param  list<UploadedFile>|Optional  $attachments
     * @param  list<string>|Optional  $mentionedUserIds
     */
    public function __construct(
        public string $body,
        public bool|Optional $isInternal,
        public string|Optional|null $messageId,
        public array|Optional $attachments,
        public array|Optional $mentionedUserIds,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:65535'],
            'message_id' => ['sometimes', 'nullable', 'string', 'max:998'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'attachments.*' => AddTicketAttachment::rules(),
            'mentioned_user_ids' => ['sometimes', 'array'],
            'mentioned_user_ids.*' => ['string', 'distinct'],
        ];
    }
}
