<?php

namespace Database\Seeders;

use App\Enums\DeskRole;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\WorkspaceRole;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A big, realistic data set for measuring performance, in its own `volume` workspace
 * (sign in as volume-admin@example.com / password). Development only: rows go in through bulk
 * inserts, so no events fire. Run it with `php artisan db:seed --class=VolumeSeeder`.
 */
class VolumeSeeder extends Seeder
{
    private const string SUBDOMAIN = 'volume';

    private const int DESKS = 5;

    private const int AGENTS = 20;

    private const int CONTACTS = 20_000;

    private const int TICKETS = 100_000;

    private const int COMMENTS = 500_000;

    private const int EVENTS = 1_000_000;

    private const int LONG_THREAD_COMMENTS = 650;

    private const int CHUNK = 1000;

    /** @var array<string, list<array<string, mixed>>> */
    private array $buffers = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The volume seeder is for development only.');
        }

        if (Workspace::query()->where('subdomain', self::SUBDOMAIN)->exists()) {
            throw new RuntimeException('The "volume" workspace already exists. Delete it first.');
        }

        DB::disableQueryLog();

        $workspace = Workspace::factory()->create(['name' => 'Volume', 'subdomain' => self::SUBDOMAIN]);
        $workspace->makeCurrent();

        $desks = $this->desks($workspace);
        $agents = $this->agents($workspace, $desks);
        $contactIds = $this->contacts($workspace);
        $this->tickets($workspace, $desks, $agents, $contactIds);

        Workspace::forgetCurrent();
    }

    /**
     * @return list<string>
     */
    private function desks(Workspace $workspace): array
    {
        $ids = [Desk::query()->value('id')];

        while (count($ids) < self::DESKS) {
            $ids[] = Desk::factory()->create(['workspace_id' => $workspace->id])->id;
        }

        return $ids;
    }

    /**
     * @param  list<string>  $deskIds
     * @return list<string>
     */
    private function agents(Workspace $workspace, array $deskIds): array
    {
        $ids = [];

        foreach (range(1, self::AGENTS) as $number) {
            $user = User::factory()->create($number === 1
                ? ['name' => 'Volume Admin', 'email' => 'volume-admin@example.com']
                : ['email' => "volume-agent-{$number}@example.com"]);

            $workspace->users()->attach($user, ['role' => ($number === 1 ? WorkspaceRole::OWNER : WorkspaceRole::AGENT)->value]);

            foreach ($deskIds as $index => $deskId) {
                if ($number === 1 || ($number + $index) % 2 === 0) {
                    DB::table('desk_user')->insert([
                        'workspace_id' => $workspace->id,
                        'desk_id' => $deskId,
                        'user_id' => $user->id,
                        'role' => ($number % 5 === 0 ? DeskRole::LEAD : DeskRole::AGENT)->value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $ids[] = $user->id;
        }

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function contacts(Workspace $workspace): array
    {
        $ids = [];
        $model = new Contact;

        foreach (range(1, self::CONTACTS) as $number) {
            $id = $model->newUniqueId();
            $ids[] = $id;

            $this->push('contacts', [
                ...$this->stringKeys(Contact::factory()->raw(['workspace_id' => $workspace->id, 'email' => "contact-{$number}@example.com"])),
                'id' => $id,
                ...$this->timestamps(),
            ]);
        }

        $this->flush('contacts');

        return $ids;
    }

    /**
     * @param  list<string>  $deskIds
     * @param  list<string>  $agentIds
     * @param  list<string>  $contactIds
     */
    private function tickets(Workspace $workspace, array $deskIds, array $agentIds, array $contactIds): void
    {
        $paragraphs = array_map(fn () => fake()->paragraph(), range(1, 200));
        $ticketModel = new Ticket;
        $commentModel = new TicketComment;
        $eventModel = new TicketEvent;
        $commentsPerTicket = self::COMMENTS / self::TICKETS;
        $eventsPerTicket = self::EVENTS / self::TICKETS;
        $statuses = TicketStatus::cases();
        $priorities = TicketPriority::cases();

        foreach (range(1, self::TICKETS) as $number) {
            $ticketId = $ticketModel->newUniqueId();
            $created = $this->timestamps();
            $status = $statuses[random_int(0, 99) < 60 ? random_int(2, 4) : random_int(0, 1)];
            $requesterId = $contactIds[array_rand($contactIds)];
            $assigneeId = random_int(0, 9) < 7 ? $agentIds[array_rand($agentIds)] : null;

            $this->push('tickets', [
                ...$this->stringKeys(Ticket::factory()->raw([
                    'workspace_id' => $workspace->id,
                    'desk_id' => $deskIds[array_rand($deskIds)],
                    'requester_id' => $requesterId,
                    'status' => $status->value,
                ])),
                'id' => $ticketId,
                'number' => $number,
                'priority' => $priorities[array_rand($priorities)]->value,
                'source' => 'web',
                'custom_fields' => '{}',
                ...$created,
            ]);

            if ($assigneeId !== null) {
                $this->push('ticket_user', ['workspace_id' => $workspace->id, 'ticket_id' => $ticketId, 'user_id' => $assigneeId, ...$created]);
            }

            $this->push('ticket_events', $this->event($eventModel, $workspace->id, $ticketId, TicketEventType::CREATED, $created['created_at'], 'contact', $requesterId));

            // Counts are random around the per-ticket averages, so totals land close to the constants.
            $commentCount = $number === 1 ? self::LONG_THREAD_COMMENTS : random_int(0, (int) ($commentsPerTicket * 2));
            $start = CarbonImmutable::parse($created['created_at']);

            for ($i = 0; $i < $commentCount; $i++) {
                $isAgent = $i % 2 === 1;
                $at = $start->addMinutes(($i + 1) * random_int(5, 600))->min(now())->format('Y-m-d H:i:s');
                $authorId = $isAgent ? ($assigneeId ?? $agentIds[0]) : $requesterId;

                $this->push('ticket_comments', [
                    ...$this->stringKeys(TicketComment::factory()->raw([
                        'workspace_id' => $workspace->id,
                        'ticket_id' => $ticketId,
                        'author_type' => $isAgent ? 'user' : 'contact',
                        'author_id' => $authorId,
                        'body' => $paragraphs[array_rand($paragraphs)],
                    ])),
                    'id' => $commentModel->newUniqueId(),
                    'is_internal' => $isAgent && random_int(0, 9) === 0,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
            }

            $eventCount = random_int(1, (int) ($eventsPerTicket * 2) - 1) - 1;

            for ($i = 0; $i < $eventCount; $i++) {
                $at = $start->addMinutes(($i + 1) * random_int(5, 600))->min(now())->format('Y-m-d H:i:s');
                $this->push('ticket_events', $this->randomEvent($eventModel, $workspace->id, $ticketId, $at, $agentIds, $status));
            }

            if ($number % 10_000 === 0) {
                $this->command->info("{$number} tickets");
            }
        }

        foreach (array_keys($this->buffers) as $table) {
            $this->flush($table);
        }

        $workspace->update(['next_ticket_number' => self::TICKETS + 1]);
    }

    /**
     * @param  list<string>  $agentIds
     * @return array<string, mixed>
     */
    private function randomEvent(TicketEvent $model, string $workspaceId, string $ticketId, string $at, array $agentIds, TicketStatus $status): array
    {
        $agentId = $agentIds[array_rand($agentIds)];
        $priority = TicketPriority::cases()[random_int(0, 3)]->value;

        [$type, $properties] = match (random_int(0, 5)) {
            0 => [TicketEventType::STATUS_CHANGED, ['from' => 'open', 'to' => $status->value]],
            1 => [TicketEventType::PRIORITY_CHANGED, ['from' => 'normal', 'to' => $priority]],
            2 => [TicketEventType::ASSIGNEE_ADDED, ['user_id' => $agentId, 'name' => 'Volume Agent']],
            3 => [TicketEventType::TAG_ADDED, ['name' => 'volume']],
            4 => [TicketEventType::COMMENT_ADDED, []],
            default => [TicketEventType::INTERNAL_NOTE_ADDED, []],
        };

        return $this->event($model, $workspaceId, $ticketId, $type, $at, 'user', $agentId, $properties);
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function event(TicketEvent $model, string $workspaceId, string $ticketId, TicketEventType $type, string $at, string $actorType, string $actorId, array $properties = []): array
    {
        return [
            ...$this->stringKeys(TicketEvent::factory()->raw([
                'workspace_id' => $workspaceId,
                'ticket_id' => $ticketId,
                'type' => $type->value,
                'properties' => json_encode($properties, JSON_FORCE_OBJECT),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'created_at' => $at,
            ])),
            'id' => $model->newUniqueId(),
        ];
    }

    /**
     * Random created and updated times across the last two years, updated never before created.
     *
     * @return array{created_at: string, updated_at: string}
     */
    private function timestamps(): array
    {
        $created = CarbonImmutable::now()->subSeconds(random_int(60, 2 * 365 * 86_400));
        $updated = $created->addSeconds(random_int(0, 30 * 86_400))->min(now());

        return ['created_at' => $created->format('Y-m-d H:i:s'), 'updated_at' => $updated->format('Y-m-d H:i:s')];
    }

    /**
     * Factory `raw()` is typed with int|string keys; attribute names are always strings.
     *
     * @param  array<int|string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function stringKeys(array $attributes): array
    {
        return array_combine(array_map(strval(...), array_keys($attributes)), $attributes);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function push(string $table, array $row): void
    {
        $this->buffers[$table][] = $row;

        if (count($this->buffers[$table]) >= self::CHUNK) {
            $this->flush($table);
        }
    }

    private function flush(string $table): void
    {
        if (in_array($table, ['ticket_user', 'ticket_comments', 'ticket_events'], true)) {
            $this->flush('tickets');
        }

        if (! empty($this->buffers[$table])) {
            DB::table($table)->insert($this->buffers[$table]);
        }

        $this->buffers[$table] = [];
    }
}
