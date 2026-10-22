<?php

namespace App\Automations\Fields;

use App\Automations\Context;
use App\Automations\FieldGroup;
use App\Automations\FieldType;
use App\Enums\SlaMetric;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\BusinessSchedule;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\SlaPolicy;
use Closure;
use Illuminate\Support\Str;

/**
 * The built-in fields of the catalog, every group but custom fields and webhook payloads.
 */
class CoreFields
{
    /**
     * @return list<Field>
     */
    public static function all(): array
    {
        $ticket = fn (string $key, string $label, FieldType $type, Closure $resolver, ?Closure $options = null): Field => new ClosureField("ticket.$key", $label, FieldGroup::TICKET, $type, $resolver, $options);
        $requester = fn (string $key, string $label, Closure $resolver): Field => new ClosureField("requester.$key", $label, FieldGroup::REQUESTER, FieldType::STRING, $resolver);
        $message = fn (string $key, string $label, FieldType $type, Closure $resolver, ?Closure $options = null): Field => new ClosureField("message.$key", $label, FieldGroup::MESSAGE, $type, $resolver, $options);
        $event = fn (string $key, string $label, FieldType $type, Closure $resolver, ?Closure $options = null): Field => new ClosureField("event.$key", $label, FieldGroup::EVENT, $type, $resolver, $options);
        $time = fn (string $key, string $label, Closure $resolver): Field => new ClosureField("time.$key", $label, FieldGroup::TIME, FieldType::DURATION, $resolver);
        $sla = fn (string $key, string $label, SlaMetric $metric): Field => new ClosureField("sla.$key", $label, FieldGroup::SLA, FieldType::ENUM, fn (Context $context): string => $context->slaState($metric), fn (): array => self::slaStates());

        $commentAuthor = fn (Context $context): ?string => ($author = $context->comment()?->author_type) === null ? null : self::actorType($author);
        $stringified = fn (mixed $value): ?string => is_scalar($value) ? (string) $value : null;

        return [
            $ticket('number', __('Number'), FieldType::NUMBER, fn (Context $c): int => $c->ticket->number),
            $ticket('subject', __('Subject'), FieldType::STRING, fn (Context $c): string => $c->ticket->subject),
            $ticket('status', __('Status'), FieldType::ENUM, fn (Context $c): string => $c->ticket->status->value, fn (): array => TicketStatus::options()),
            $ticket('priority', __('Priority'), FieldType::ENUM, fn (Context $c): string => $c->ticket->priority->value, fn (): array => TicketPriority::options()),
            $ticket('desk', __('Desk'), FieldType::DESK, fn (Context $c): string => $c->ticket->desk_id, fn (): array => Desk::query()->orderBy('name')->pluck('name', 'id')->all()),
            $ticket('assignees', __('Assignees'), FieldType::USERS, fn (Context $c): array => $c->memo('assignees', fn (): array => $c->ticket->assignees()->pluck('users.id')->all())),
            $ticket('tags', __('Tags'), FieldType::MULTI_ENUM, fn (Context $c): array => $c->memo('tags', fn (): array => $c->ticket->tags()->pluck('tags.name')->all())),
            $ticket('due_at', __('Due date'), FieldType::DATETIME, fn (Context $c) => $c->ticket->due_at),
            $ticket('source', __('Source'), FieldType::ENUM, fn (Context $c): string => $c->ticket->source->value, fn (): array => TicketSource::options()),
            $ticket('mailbox', __('Mailbox'), FieldType::ENUM, fn (Context $c): ?string => $c->ticket->mailbox_id, fn (): array => Mailbox::query()->orderBy('name')->pluck('name', 'id')->all()),
            $ticket('sla_policy', __('SLA policy'), FieldType::ENUM, fn (Context $c): ?string => $c->ticket->effectiveSlaPolicy()?->getKey(), fn (): array => SlaPolicy::query()->orderBy('name')->pluck('name', 'id')->all()),

            $requester('name', __('Name'), fn (Context $c): string => $c->ticket->requester->name),
            $requester('email', __('Email'), fn (Context $c): string => $c->ticket->requester->email),
            $requester('email_domain', __('Email domain'), fn (Context $c): string => Str::lower(Str::after($c->ticket->requester->email, '@'))),

            $message('body', __('Body'), FieldType::TEXT, fn (Context $c): ?string => $c->comment()?->body),
            $message('author_type', __('Author type'), FieldType::ENUM, $commentAuthor, fn (): array => self::actorTypes()),
            $message('is_internal', __('Is internal note'), FieldType::BOOLEAN, fn (Context $c): ?bool => $c->comment()?->is_internal),
            $message('has_attachments', __('Has attachments'), FieldType::BOOLEAN, fn (Context $c): ?bool => $c->comment()?->attachments->isNotEmpty()),
            $message('attachment_filenames', __('Attachment filenames'), FieldType::TEXT, fn (Context $c): ?string => $c->comment()?->attachments->pluck('filename')->implode("\n")),

            $event('field', __('Changed field'), FieldType::STRING, fn (Context $c): ?string => $c->change()['field'] ?? null),
            $event('from', __('Previous value'), FieldType::STRING, fn (Context $c): ?string => $stringified($c->change()['from'] ?? null)),
            $event('to', __('New value'), FieldType::STRING, fn (Context $c): ?string => $stringified($c->change()['to'] ?? null)),
            $event('actor_type', __('Actor type'), FieldType::ENUM, fn (Context $c): ?string => $c->event === null ? null : ($c->event->actor_type === null ? 'harbor' : self::actorType($c->event->actor_type)), fn (): array => self::actorTypes() + ['harbor' => __('Harbor')]),
            $event('actor', __('Actor'), FieldType::USER, fn (Context $c): ?string => $c->event?->actor_type === 'user' ? $c->event->actor_id : null),

            $time('since_created', __('Time since created'), fn (Context $c, ?BusinessSchedule $s): ?int => $c->minutesSince($c->ticket->created_at, $s)),
            $time('since_updated', __('Time since last update'), fn (Context $c, ?BusinessSchedule $s): ?int => $c->minutesSince($c->ticket->updated_at, $s)),
            $time('since_requester_reply', __('Time since last requester reply'), fn (Context $c, ?BusinessSchedule $s): ?int => $c->minutesSince($c->lastReplyAt('contact'), $s)),
            $time('since_agent_reply', __('Time since last agent reply'), fn (Context $c, ?BusinessSchedule $s): ?int => $c->minutesSince($c->lastReplyAt('user'), $s)),
            $time('since_status_changed', __('Time since status changed'), fn (Context $c, ?BusinessSchedule $s): ?int => $c->minutesSince($c->statusChangedAt(), $s)),
            $time('until_due', __('Time until due'), fn (Context $c, ?BusinessSchedule $s): ?int => $c->minutesUntil($c->ticket->due_at, $s)),
            $time('until_sla_due', __('Time until SLA due'), fn (Context $c, ?BusinessSchedule $s): ?int => $c->minutesUntil($c->nextSlaDueAt(), $s)),

            $sla('first_response', __('First response'), SlaMetric::FIRST_RESPONSE),
            $sla('resolution', __('Resolution'), SlaMetric::RESOLUTION),
        ];
    }

    /**
     * A webhook payload value at a dot path, read as the given type (string, number or boolean).
     */
    public static function webhookPayload(string $path, FieldType $type): Field
    {
        $prefix = match ($type) {
            FieldType::NUMBER => 'payload_number',
            FieldType::BOOLEAN => 'payload_boolean',
            default => 'payload',
        };

        return new ClosureField(
            key: "webhook.$prefix.$path",
            label: $path,
            group: FieldGroup::WEBHOOK,
            type: $type,
            resolver: fn (Context $context): mixed => match ($type) {
                FieldType::NUMBER => is_numeric($value = data_get($context->payload, $path)) ? $value + 0 : null,
                FieldType::BOOLEAN => filter_var(data_get($context->payload, $path), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
                default => is_scalar($value = data_get($context->payload, $path)) ? (string) $value : null,
            },
        );
    }

    /**
     * The people-facing name of a morph alias: agents are users, requesters are contacts.
     */
    protected static function actorType(string $morphAlias): string
    {
        return match ($morphAlias) {
            'user' => 'agent',
            'contact' => 'requester',
            default => $morphAlias,
        };
    }

    /**
     * @return array<string, string>
     */
    protected static function actorTypes(): array
    {
        return ['agent' => __('Agent'), 'requester' => __('Requester'), 'automation' => __('Automation')];
    }

    /**
     * @return array<string, string>
     */
    protected static function slaStates(): array
    {
        return ['active' => __('Active'), 'paused' => __('Paused'), 'met' => __('Met'), 'breached' => __('Breached'), 'none' => __('No target')];
    }
}
