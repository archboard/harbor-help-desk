<?php

namespace App\Data\Tickets;

use App\Actions\Tickets\AddTicketAttachment;
use App\Actions\Tickets\CreateTicket;
use App\Data\InputData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Optional;

/**
 * Who may set `assignee_ids`, `mailbox_id` and `message_id`, and the desk's custom fields, are
 * checked by {@see CreateTicket}.
 */
class CreateTicketData extends InputData
{
    /**
     * @param  array{name: string, email: string}|Optional  $requester  Found or created by email.
     * @param  list<string>|Optional  $assigneeIds
     * @param  list<UploadedFile>|Optional  $attachments
     * @param  array<string, mixed>|Optional  $customFields  Values keyed by custom field id.
     */
    public function __construct(
        #[Max(255)]
        public string $subject,
        public TicketStatus|Optional $status,
        public TicketPriority|Optional $priority,
        public string|Optional|null $dueAt,
        public string|Optional $requesterId,
        public array|Optional $requester,
        public array|Optional $assigneeIds,
        public string|Optional|null $mailboxId,
        public string|Optional|null $messageId,
        public string|Optional|null $body,
        public array|Optional $attachments,
        public array|Optional $customFields,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'due_at' => ['sometimes', 'nullable', 'date'],
            'requester_id' => ['required_without:requester', 'string', Rule::exists('contacts', 'id')->where('workspace_id', Workspace::current()?->getKey())],
            'requester' => ['required_without:requester_id', 'array:name,email'],
            'requester.email' => ['required_with:requester', 'email', 'max:255'],
            'requester.name' => ['required_with:requester', 'string', 'max:255'],
            'mailbox_id' => ['sometimes', 'nullable', 'string', Rule::exists('mailboxes', 'id')->where('workspace_id', Workspace::current()?->getKey())],
            'message_id' => ['sometimes', 'nullable', 'string', 'max:998'],
            'body' => ['required_with:attachments', 'nullable', 'string', 'max:65535'],
            'attachments' => ['sometimes', 'array', 'max:20'],
            'attachments.*' => AddTicketAttachment::rules(),
        ];
    }
}
