<?php

use App\Automations\AutomationRegistry;
use App\Automations\Catalog;
use App\Automations\Triggers\TicketCreated;
use App\Enums\AutomationRunStatus;
use App\Enums\TicketEventType;
use App\Enums\TicketPriority;
use App\Enums\WorkspaceRole;
use App\Jobs\RunAutomations;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\Automations\SetPriorityAction;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    app()->instance(AutomationRegistry::class, new AutomationRegistry([TicketCreated::class], [SetPriorityAction::class]));

    $this->acme = Workspace::factory()->create(['subdomain' => 'acme']);
    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
});

/**
 * An enabled automation of the workspace that sets new tickets' priority.
 */
function priorityAutomationIn(Workspace $workspace, string $priority): Automation
{
    return $workspace->execute(fn () => Automation::factory()->enabled()
        ->triggeredBy('ticket.created', 'fake.set_priority', ['priority' => $priority])->create(['name' => "Sets $priority"]));
}

function newTicketIn(Workspace $workspace): Ticket
{
    return $workspace->execute(function () {
        $ticket = Ticket::factory()->create(['priority' => TicketPriority::NORMAL]);
        $ticket->recordEvent(TicketEventType::CREATED, null);

        return $ticket;
    });
}

test('events are queued on the automations queue and the ticket\'s jobs never overlap', function () {
    Queue::fake();
    config(['queue.default' => 'redis']);

    $ticket = $this->acme->execute(fn () => Ticket::factory()->create());
    $this->acme->execute(fn () => $ticket->recordEvent(TicketEventType::CREATED, null));

    Queue::assertPushedOn('automations', RunAutomations::class, function (RunAutomations $job) use ($ticket) {
        $middleware = $job->middleware();

        return $job->ticketEvent->ticket_id === $ticket->id
            && count($middleware) === 1
            && $middleware[0] instanceof WithoutOverlapping
            && $middleware[0]->key === "ticket:{$ticket->id}";
    });
});

test('an automation only runs for tickets of its own workspace', function () {
    $acmeAutomation = priorityAutomationIn($this->acme, 'high');
    $otherAutomation = priorityAutomationIn($this->other, 'low');

    $acmeTicket = newTicketIn($this->acme);
    $otherTicket = newTicketIn($this->other);

    $runs = AutomationRun::withoutGlobalScope('workspace')->get();

    expect($runs)->toHaveCount(2)
        ->and($runs->firstWhere('automation_id', $acmeAutomation->id))
        ->workspace_id->toBe($this->acme->id)
        ->ticket_id->toBe($acmeTicket->id)
        ->status->toBe(AutomationRunStatus::COMPLETED)
        ->and($runs->firstWhere('automation_id', $otherAutomation->id))
        ->workspace_id->toBe($this->other->id)
        ->ticket_id->toBe($otherTicket->id)
        ->and($this->acme->execute(fn () => $acmeTicket->refresh()->priority))->toBe(TicketPriority::HIGH)
        ->and($this->other->execute(fn () => $otherTicket->refresh()->priority))->toBe(TicketPriority::LOW)
        ->and($this->acme->execute(fn () => AutomationRun::count()))->toBe(1)
        ->and($this->other->execute(fn () => AutomationRun::count()))->toBe(1);
});

test('a workspace without automations is not touched by another workspace\'s', function () {
    priorityAutomationIn($this->other, 'low');

    $ticket = newTicketIn($this->acme);

    expect(AutomationRun::withoutGlobalScope('workspace')->count())->toBe(0)
        ->and($this->acme->execute(fn () => $ticket->refresh()->priority))->toBe(TicketPriority::NORMAL);
});

test('the queued job runs inside the workspace of the event', function () {
    config(['queue.default' => 'database']);
    $automation = priorityAutomationIn($this->acme, 'high');
    priorityAutomationIn($this->other, 'low');

    $ticket = newTicketIn($this->acme);

    expect(AutomationRun::withoutGlobalScope('workspace')->count())->toBe(0);

    // The worker has no current workspace of its own: it takes it from the job.
    Workspace::forgetCurrent();
    Artisan::call('queue:work', ['--once' => true, '--queue' => 'automations']);

    $run = AutomationRun::withoutGlobalScope('workspace')->sole();

    expect($run)->workspace_id->toBe($this->acme->id)
        ->automation_id->toBe($automation->id)
        ->and($this->acme->execute(fn () => $ticket->refresh()->priority))->toBe(TicketPriority::HIGH);
});

test('the registry and catalog keep no workspace data between workspaces', function () {
    $admins = collect([$this->acme, $this->other])->map(function (Workspace $workspace) {
        $admin = User::factory()->create();
        $workspace->users()->attach($admin, ['role' => WorkspaceRole::ADMIN]);
        $workspace->execute(fn () => Desk::factory()->create(['name' => "{$workspace->subdomain} desk"]));

        return $admin;
    });

    $catalog = fn (Workspace $workspace, User $admin) => $workspace->execute(fn () => app(Catalog::class)->build($admin));
    $deskNames = fn ($data) => collect($data->fieldGroups)->pluck('fields')->flatten()->firstWhere('key', 'ticket.desk')->options;

    $first = $catalog($this->acme, $admins[0]);
    $second = $catalog($this->other, $admins[1]);
    $again = $catalog($this->acme, $admins[0]);

    expect(collect($deskNames($first))->pluck('label')->all())->toContain('acme desk')->not->toContain('other desk')
        ->and(collect($deskNames($second))->pluck('label')->all())->toContain('other desk')->not->toContain('acme desk')
        ->and(collect($deskNames($again))->pluck('label')->all())->toContain('acme desk')->not->toContain('other desk');
});

test('runs older than 30 days are pruned in every workspace', function () {
    priorityAutomationIn($this->acme, 'high');
    priorityAutomationIn($this->other, 'low');
    newTicketIn($this->acme);
    newTicketIn($this->other);
    AutomationRun::withoutGlobalScope('workspace')->where('workspace_id', $this->acme->id)->update(['created_at' => now()->subDays(31)]);

    Artisan::call('model:prune', ['--model' => AutomationRun::class]);

    expect(AutomationRun::withoutGlobalScope('workspace')->pluck('workspace_id')->all())->toBe([$this->other->id]);
});
