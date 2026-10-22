<?php

namespace App\Automations;

use App\Enums\SlaMetric;
use App\Enums\TicketEventType;
use App\Models\BusinessSchedule;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\TicketSlaTarget;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;

/**
 * Everything one evaluation can read: the ticket as it is in the database now, the event or
 * webhook payload that triggered it, and a memo so a value several rules need is queried once.
 */
class Context
{
    public readonly Ticket $ticket;

    /** @var array<string, mixed> */
    protected array $memo = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(Ticket $ticket, public readonly ?TicketEvent $event = null, public readonly array $payload = [])
    {
        $this->ticket = Ticket::query()->withTrashed()->with(['requester', 'desk'])->findOrFail($ticket->getKey());
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function memo(string $key, Closure $callback): mixed
    {
        return array_key_exists($key, $this->memo) ? $this->memo[$key] : $this->memo[$key] = $callback();
    }

    /**
     * The comment a comment event is about, even if it has been deleted since.
     */
    public function comment(): ?TicketComment
    {
        $commentEvents = [TicketEventType::COMMENT_ADDED, TicketEventType::INTERNAL_NOTE_ADDED, TicketEventType::COMMENT_EDITED];

        if ($this->event === null || ! in_array($this->event->type, $commentEvents, true)) {
            return null;
        }

        return $this->memo('comment', fn (): ?TicketComment => TicketComment::query()
            ->withTrashed()->with('attachments')->find($this->event->properties['comment_id'] ?? null));
    }

    /**
     * The field the event changed with its old and new value, or null for events that change no field.
     * Tags and assignees have no old or new list, so an add is a change to the tag name or user id.
     *
     * @return array{field: string, from: mixed, to: mixed}|null
     */
    public function change(): ?array
    {
        if ($this->event === null) {
            return null;
        }

        $properties = $this->event->properties;
        $change = fn (string $field, mixed $from, mixed $to): array => ['field' => $field, 'from' => $from, 'to' => $to];

        return match ($this->event->type) {
            TicketEventType::SUBJECT_CHANGED => $change('ticket.subject', $properties['from'], $properties['to']),
            TicketEventType::STATUS_CHANGED => $change('ticket.status', $properties['from'], $properties['to']),
            TicketEventType::PRIORITY_CHANGED => $change('ticket.priority', $properties['from'], $properties['to']),
            TicketEventType::DUE_DATE_CHANGED => $change('ticket.due_at', $properties['from'], $properties['to']),
            TicketEventType::SLA_POLICY_CHANGED => $change('ticket.sla_policy', $properties['from'], $properties['to']),
            TicketEventType::DESK_CHANGED => $change('ticket.desk', $properties['from_id'], $properties['to_id']),
            TicketEventType::CUSTOM_FIELD_CHANGED => $change("custom_field.{$properties['field_id']}", $properties['from'], $properties['to']),
            TicketEventType::TAG_ADDED => $change('ticket.tags', null, $properties['name']),
            TicketEventType::TAG_REMOVED => $change('ticket.tags', $properties['name'], null),
            TicketEventType::ASSIGNEE_ADDED => $change('ticket.assignees', null, $properties['user_id']),
            TicketEventType::ASSIGNEE_REMOVED => $change('ticket.assignees', $properties['user_id'], null),
            default => null,
        };
    }

    /**
     * When the last public reply by an author type (`user` or `contact`) was posted.
     */
    public function lastReplyAt(string $authorType): ?CarbonImmutable
    {
        return $this->memo("last_reply.$authorType", fn (): ?CarbonImmutable => $this->ticket->comments()
            ->where('author_type', $authorType)->where('is_internal', false)->latest()->first()?->created_at);
    }

    public function statusChangedAt(): CarbonImmutable
    {
        return $this->memo('status_changed_at', fn (): CarbonImmutable => $this->ticket->events()
            ->where('type', TicketEventType::STATUS_CHANGED)->latest()->first()?->created_at ?? $this->ticket->created_at);
    }

    /**
     * `active`, `paused`, `met`, `breached`, or `none` when the ticket has no clock for the metric.
     */
    public function slaState(SlaMetric $metric): string
    {
        $target = $this->slaTargets()->firstWhere('metric', $metric);

        return match (true) {
            $target === null => 'none',
            $target->breached_at !== null => 'breached',
            $target->achieved_at !== null => 'met',
            $target->paused_at !== null => 'paused',
            default => 'active',
        };
    }

    /**
     * The soonest due time among the SLA clocks still running.
     */
    public function nextSlaDueAt(): ?CarbonImmutable
    {
        return $this->slaTargets()
            ->filter(fn (TicketSlaTarget $target): bool => $target->due_at !== null && $target->achieved_at === null && $target->breached_at === null)
            ->min('due_at');
    }

    /**
     * Minutes from `$at` until now, in business time when a schedule is given. Null without a time.
     */
    public function minutesSince(?CarbonInterface $at, ?BusinessSchedule $schedule): ?int
    {
        return $at === null ? null : BusinessSchedule::minutesBetweenFor($schedule, $at, CarbonImmutable::now());
    }

    /**
     * Minutes from now until `$at`, negative once it has passed. Null without a time.
     */
    public function minutesUntil(?CarbonInterface $at, ?BusinessSchedule $schedule): ?int
    {
        if ($at === null) {
            return null;
        }

        $now = CarbonImmutable::now();

        return $at > $now
            ? BusinessSchedule::minutesBetweenFor($schedule, $now, $at)
            : -BusinessSchedule::minutesBetweenFor($schedule, $at, $now);
    }

    /**
     * @return Collection<int, TicketSlaTarget>
     */
    protected function slaTargets(): Collection
    {
        return $this->memo('sla_targets', fn (): Collection => $this->ticket->slaTargets()->get());
    }
}
