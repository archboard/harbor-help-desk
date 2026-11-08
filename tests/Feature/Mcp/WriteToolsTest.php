<?php

use App\Enums\DeskRole;
use App\Events\TicketActivityRecorded;
use App\Jobs\SendTicketReplyEmail;
use App\Mcp\Servers\HarborServer;
use App\Mcp\Tools\ApplyMacroTool;
use App\Mcp\Tools\BulkUpdateTicketsTool;
use App\Mcp\Tools\ClaimNextTicketTool;
use App\Mcp\Tools\CreateTicketTool;
use App\Mcp\Tools\MoveTicketTool;
use App\Mcp\Tools\ReplyToTicketTool;
use App\Mcp\Tools\SetReminderTool;
use App\Mcp\Tools\SetTicketAssigneesTool;
use App\Mcp\Tools\SetTicketTagsTool;
use App\Mcp\Tools\UpdateTicketTool;
use App\Models\Contact;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    ['desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->ticket = Ticket::factory()->create(['desk_id' => $this->desk->id, 'subject' => 'Printer on fire']);
    $this->secretDesk = Desk::factory()->create(['name' => 'Secret desk']);
    $this->hidden = Ticket::factory()->create(['desk_id' => $this->secretDesk->id, 'subject' => 'Hidden matter']);
});

function callTool(string $tool, array $arguments)
{
    return HarborServer::actingAs(test()->agent)->tool($tool, $arguments);
}

test('create_ticket opens a ticket and validation errors come back as tool errors', function () {
    $contact = Contact::factory()->create();

    callTool(CreateTicketTool::class, ['desk_id' => $this->desk->id, 'subject' => 'New one', 'requester_id' => $contact->id])
        ->assertOk()->assertSee('New one');
    expect(Ticket::where('subject', 'New one')->sole()->source->value)->toBe('api');

    callTool(CreateTicketTool::class, ['desk_id' => $this->desk->id, 'requester_id' => $contact->id])->assertHasErrors();
    callTool(CreateTicketTool::class, ['desk_id' => 'dsk_missing', 'subject' => 'x', 'requester_id' => $contact->id])
        ->assertHasErrors(['Desk dsk_missing not found.']);
    callTool(CreateTicketTool::class, ['desk_id' => $this->secretDesk->id, 'subject' => 'x', 'requester_id' => $contact->id])->assertHasErrors();
    expect(Ticket::where('subject', 'x')->exists())->toBeFalse();
});

test('update_ticket records an event and rejects invalid input', function () {
    Event::fake([TicketActivityRecorded::class]);

    callTool(UpdateTicketTool::class, ['ticket' => "#{$this->ticket->number}", 'subject' => 'Renamed'])->assertOk()->assertSee('Renamed');
    expect($this->ticket->refresh()->subject)->toBe('Renamed');
    Event::assertDispatched(TicketActivityRecorded::class);

    callTool(UpdateTicketTool::class, ['ticket' => $this->ticket->id, 'status' => 'bogus'])->assertHasErrors();
});

test('move_ticket moves between desks and is denied for a desk the agent is not on', function () {
    $other = Desk::factory()->create(['name' => 'Billing']);
    $other->users()->attach($this->agent, ['role' => DeskRole::AGENT, 'workspace_id' => $other->workspace_id]);

    callTool(MoveTicketTool::class, ['ticket' => $this->ticket->id, 'desk_id' => $other->id])->assertOk();
    expect($this->ticket->refresh()->desk_id)->toBe($other->id);

    callTool(MoveTicketTool::class, ['ticket' => $this->ticket->id, 'desk_id' => $this->secretDesk->id])->assertHasErrors();
    callTool(MoveTicketTool::class, ['ticket' => $this->ticket->id, 'desk_id' => 'dsk_missing'])->assertHasErrors(['Desk dsk_missing not found.']);
    expect($this->ticket->refresh()->desk_id)->toBe($other->id);
});

test('set_ticket_assignees and set_ticket_tags replace and validate', function () {
    callTool(SetTicketAssigneesTool::class, ['ticket' => $this->ticket->id, 'user_ids' => [$this->agent->id]])->assertOk();
    expect($this->ticket->assignees()->pluck('users.id')->all())->toBe([$this->agent->id]);
    callTool(SetTicketAssigneesTool::class, ['ticket' => $this->ticket->id])->assertHasErrors();

    callTool(SetTicketTagsTool::class, ['ticket' => $this->ticket->id, 'names' => ['vip', 'bug']])->assertOk()->assertSee('vip');
    expect($this->ticket->tags()->count())->toBe(2);
    callTool(SetTicketTagsTool::class, ['ticket' => $this->ticket->id, 'names' => 'vip'])->assertHasErrors();
});

test('reply_to_ticket queues the email for a public reply only', function () {
    Bus::fake([SendTicketReplyEmail::class]);

    callTool(ReplyToTicketTool::class, ['ticket' => $this->ticket->id, 'body' => 'Note', 'is_internal' => true])->assertOk();
    Bus::assertNotDispatched(SendTicketReplyEmail::class);

    callTool(ReplyToTicketTool::class, ['ticket' => $this->ticket->id, 'body' => 'Hello customer'])->assertOk()->assertSee('Hello customer');
    Bus::assertDispatched(SendTicketReplyEmail::class);

    callTool(ReplyToTicketTool::class, ['ticket' => $this->ticket->id])->assertHasErrors();
});

test('reply_to_ticket rejects a stale last_seen_comment_id', function () {
    Bus::fake([SendTicketReplyEmail::class]);
    TicketComment::factory()->create(['ticket_id' => $this->ticket->id, 'body' => 'Customer chimed in']);

    callTool(ReplyToTicketTool::class, ['ticket' => $this->ticket->id, 'body' => 'Late', 'last_seen_comment_id' => null])
        ->assertHasErrors();
    expect(TicketComment::where('body', 'Late')->exists())->toBeFalse();
    Bus::assertNotDispatched(SendTicketReplyEmail::class);
});

test('apply_macro returns the text without commenting', function () {
    $macro = Macro::factory()->create(['body' => 'Have you tried turning it off?', 'actions' => ['status' => 'pending']]);

    callTool(ApplyMacroTool::class, ['ticket' => $this->ticket->id, 'macro' => $macro->id])
        ->assertOk()->assertSee('Have you tried turning it off?');
    expect($this->ticket->refresh()->status->value)->toBe('pending')
        ->and($this->ticket->comments()->count())->toBe(0);

    callTool(ApplyMacroTool::class, ['ticket' => $this->ticket->id, 'macro' => 'mac_missing'])->assertHasErrors(['Macro mac_missing not found.']);
});

test('set_reminder creates a reminder and validates the time', function () {
    callTool(SetReminderTool::class, ['ticket' => $this->ticket->id, 'remind_at' => now()->addDay()->toIso8601String(), 'note' => 'Chase'])
        ->assertOk()->assertSee('Chase');
    expect($this->ticket->reminders()->count())->toBe(1);

    callTool(SetReminderTool::class, ['ticket' => $this->ticket->id, 'remind_at' => now()->subDay()->toIso8601String()])->assertHasErrors();
});

test('claim_next_ticket assigns the next ticket and reports an empty queue', function () {
    callTool(ClaimNextTicketTool::class, [])->assertOk()->assertSee('Printer on fire');
    expect($this->ticket->assignees()->count())->toBe(1);

    callTool(ClaimNextTicketTool::class, [])->assertOk()->assertSee('No tickets are waiting.')->assertDontSee('Hidden matter');
});

test('bulk_update_tickets changes one thing on many tickets and has no delete', function () {
    $second = Ticket::factory()->create(['desk_id' => $this->desk->id]);

    callTool(BulkUpdateTicketsTool::class, ['ticket_ids' => [$this->ticket->id, $second->id], 'priority' => 'high'])
        ->assertOk()->assertSee('"updated":2');
    expect($second->refresh()->priority->value)->toBe('high');

    callTool(BulkUpdateTicketsTool::class, ['ticket_ids' => [$this->ticket->id], 'delete' => true])->assertHasErrors();
    expect($this->ticket->fresh())->not->toBeNull();
    callTool(BulkUpdateTicketsTool::class, ['ticket_ids' => [$this->ticket->id]])->assertHasErrors();
});

test('write tools cannot reach a ticket on a desk the agent is not on', function (string $tool, array $arguments) {
    Bus::fake();

    callTool($tool, ['ticket' => $this->hidden->id, ...$arguments])
        ->assertHasErrors(["Ticket {$this->hidden->id} not found."]);
    callTool($tool, ['ticket' => (string) $this->hidden->number, ...$arguments])->assertDontSee('Hidden matter');

    expect($this->hidden->refresh()->subject)->toBe('Hidden matter')
        ->and($this->hidden->comments()->count())->toBe(0)
        ->and($this->hidden->assignees()->count())->toBe(0);
    Bus::assertNothingDispatched();
})->with([
    'update' => [UpdateTicketTool::class, ['subject' => 'Pwned']],
    'move' => [MoveTicketTool::class, ['desk_id' => 'dsk_x']],
    'assignees' => [SetTicketAssigneesTool::class, ['user_ids' => []]],
    'tags' => [SetTicketTagsTool::class, ['names' => ['x']]],
    'reply' => [ReplyToTicketTool::class, ['body' => 'Pwned']],
    'macro' => [ApplyMacroTool::class, ['macro' => 'mac_x']],
    'reminder' => [SetReminderTool::class, ['remind_at' => '2999-01-01T00:00:00Z']],
]);

test('bulk_update_tickets and claim_next_ticket ignore tickets the agent cannot see', function () {
    callTool(BulkUpdateTicketsTool::class, ['ticket_ids' => [$this->hidden->id], 'priority' => 'high'])->assertHasErrors();
    expect($this->hidden->refresh()->priority->value)->not->toBe('high');

    $this->ticket->delete();
    callTool(ClaimNextTicketTool::class, [])->assertSee('No tickets are waiting.');
    expect($this->hidden->assignees()->count())->toBe(0);
});

test('workspace A cannot touch workspace B tickets through write tools', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other'])->makeCurrent();
    $theirDesk = Desk::where('workspace_id', $other->id)->first();
    $theirs = Ticket::factory()->create(['desk_id' => $theirDesk->id, 'subject' => 'Other workspace']);
    Workspace::where('subdomain', 'acme')->first()->makeCurrent();

    callTool(UpdateTicketTool::class, ['ticket' => $theirs->id, 'subject' => 'Pwned'])->assertHasErrors();
    callTool(ReplyToTicketTool::class, ['ticket' => $theirs->id, 'body' => 'Pwned'])->assertHasErrors();
    callTool(BulkUpdateTicketsTool::class, ['ticket_ids' => [$theirs->id], 'priority' => 'high'])->assertHasErrors();
    callTool(MoveTicketTool::class, ['ticket' => $this->ticket->id, 'desk_id' => $theirDesk->id])->assertHasErrors();
    callTool(CreateTicketTool::class, ['desk_id' => $theirDesk->id, 'subject' => 'x', 'requester_id' => 'ctc_x'])->assertHasErrors();
    callTool(ClaimNextTicketTool::class, [])->assertDontSee('Other workspace');

    $other->makeCurrent();
    expect($theirs->refresh()->subject)->toBe('Other workspace')->and($theirs->comments()->count())->toBe(0);
});
