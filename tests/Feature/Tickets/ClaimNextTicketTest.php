<?php

use App\Actions\Tickets\ClaimNextTicket;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->desk = Desk::factory()->create();
    $this->agent = deskMember($this->workspace, $this->desk);
});

function claim(User $user): ?Ticket
{
    return app(ClaimNextTicket::class)->handle($user);
}

test('claims the highest priority then oldest ticket and assigns it', function () {
    $old = Ticket::factory()->for($this->desk)->create(['priority' => TicketPriority::HIGH, 'created_at' => now()->subDays(3)]);
    $urgentNew = Ticket::factory()->for($this->desk)->create(['priority' => TicketPriority::URGENT, 'created_at' => now()->subDay()]);
    $urgentOld = Ticket::factory()->for($this->desk)->create(['priority' => TicketPriority::URGENT, 'created_at' => now()->subDays(2)]);

    expect(claim($this->agent)->is($urgentOld))->toBeTrue()
        ->and($urgentOld->assignees()->pluck('users.id')->all())->toBe([$this->agent->id])
        ->and($this->desk->users()->find($this->agent->id)->pivot->last_assigned_at)->not->toBeNull()
        ->and(claim($this->agent)->is($urgentNew))->toBeTrue()
        ->and(claim($this->agent)->is($old))->toBeTrue()
        ->and(claim($this->agent))->toBeNull();
});

test('ignores away status and rotation', function () {
    $this->workspace->users()->updateExistingPivot($this->agent->id, ['away_at' => now()]);
    $this->desk->users()->updateExistingPivot($this->agent->id, ['in_rotation' => false]);
    $ticket = Ticket::factory()->for($this->desk)->create();

    expect(claim($this->agent)->is($ticket))->toBeTrue();
});

test('skips other desks, archived desks, assigned and non-open tickets', function () {
    $otherDesk = Desk::factory()->create();
    $archived = Desk::factory()->create();
    $archivedMember = deskMember($this->workspace, $archived);
    $archived->users()->attach($this->agent, ['role' => 'agent', 'workspace_id' => $this->workspace->id]);
    Ticket::factory()->for($otherDesk)->create();
    Ticket::factory()->for($archived)->create();
    $archived->delete();
    Ticket::factory()->for($this->desk)->create(['status' => TicketStatus::SOLVED]);
    Ticket::factory()->for($this->desk)->create()->assignees()->attach($archivedMember->id, ['workspace_id' => $this->workspace->id]);

    expect(claim($this->agent))->toBeNull();
});

test('workspace admins who are not on the desk cannot claim its tickets', function () {
    $admin = User::factory()->create();
    $this->workspace->users()->attach($admin, ['role' => 'admin']);
    Ticket::factory()->for($this->desk)->create();

    expect(claim($admin))->toBeNull();
});

test('does not claim tickets from another workspace', function () {
    $other = Workspace::factory()->create()->makeCurrent();
    $otherDesk = Desk::factory()->create();
    Ticket::factory()->for($otherDesk)->create();
    $this->workspace->makeCurrent();

    expect(claim($this->agent))->toBeNull();
});

test('the claim query skips rows locked by another transaction', function () {
    Ticket::factory()->for($this->desk)->create();
    DB::enableQueryLog();
    claim($this->agent);

    // A second connection can't see tickets created inside the test transaction, so assert the lock clause.
    expect(collect(DB::getQueryLog())->pluck('query')->contains(fn ($sql) => str_contains($sql, 'for update skip locked')))->toBeTrue();
});

test('the web endpoint redirects to the ticket or back with a toast', function () {
    $this->actingAs($this->agent);

    $this->post(route('tickets.claim'))->assertRedirect();

    $ticket = Ticket::factory()->for($this->desk)->create();
    $this->post(route('tickets.claim'))->assertRedirect(route('tickets.show', $ticket));
});
