<?php

namespace App\Automations\Triggers;

use App\Automations\Context;
use App\Automations\FieldGroup;
use App\Enums\TicketEventType;
use App\Models\Contact;
use App\Models\TicketEvent;
use App\Models\User;

/**
 * A reply or an internal note was added. `author` is who wrote it: `requester` (a contact),
 * `agent` (a user) or `any`. A comment written by an automation or by Harbor itself is neither
 * a requester's nor an agent's, so only `any` matches it. `visibility` is `public` for replies,
 * `internal` for notes, or `any`. Both default to `any`.
 */
class CommentAdded implements Trigger
{
    public function key(): string
    {
        return 'ticket.comment_added';
    }

    public function label(): string
    {
        return __('A message is added');
    }

    public function configRules(): array
    {
        return [
            'author' => ['sometimes', 'in:requester,agent,any'],
            'visibility' => ['sometimes', 'in:public,internal,any'],
        ];
    }

    public function fieldGroups(): array
    {
        return [FieldGroup::MESSAGE];
    }

    public function matches(TicketEvent $event, array $config): bool
    {
        $visibility = match ($event->type) {
            TicketEventType::COMMENT_ADDED => 'public',
            TicketEventType::INTERNAL_NOTE_ADDED => 'internal',
            default => null,
        };

        if ($visibility === null || ! in_array($config['visibility'] ?? 'any', ['any', $visibility], true)) {
            return false;
        }

        return match ($config['author'] ?? 'any') {
            'requester' => $event->actor instanceof Contact,
            'agent' => $event->actor instanceof User,
            default => true,
        };
    }

    public function placeholders(): array
    {
        return [
            'message.body' => __('Message body'),
            'message.author_name' => __('Message author name'),
        ];
    }

    public function placeholderValues(Context $context): array
    {
        $comment = $context->comment();

        return [
            'message.body' => $comment?->body ?? '',
            'message.author_name' => $comment?->author?->getAttribute('name') ?? '',
        ];
    }
}
