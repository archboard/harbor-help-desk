<?php

use App\Mcp\Prompts\DraftReplyPrompt;
use App\Mcp\Prompts\SummarizeTicketPrompt;
use App\Mcp\Prompts\TriageQueuePrompt;
use App\Mcp\Servers\HarborServer;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Workspace;

beforeEach(function () {
    ['desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->ticket = Ticket::factory()->create(['desk_id' => $this->desk->id, 'subject' => 'Printer on fire']);
});

test('summarize_ticket embeds the ticket and marks internal notes', function () {
    TicketComment::factory()->internal()->create(['ticket_id' => $this->ticket->id, 'body' => 'Secret note']);

    HarborServer::actingAs($this->agent)->prompt(SummarizeTicketPrompt::class, ['ticket' => "#{$this->ticket->number}"])
        ->assertOk()
        ->assertSee(['Printer on fire', 'Secret note', '"is_internal": true', 'get_ticket']);
});

test('draft_reply tells the client to offer a note or a reply', function () {
    HarborServer::actingAs($this->agent)->prompt(DraftReplyPrompt::class, ['ticket' => $this->ticket->id])
        ->assertOk()
        ->assertSee(['Printer on fire', 'reply_to_ticket', 'internal note', 'last_seen_comment_id']);
});

test('ticket prompts do not resolve tickets the user cannot see', function () {
    $hidden = Ticket::factory()->create(['desk_id' => Desk::factory()->create(), 'subject' => 'Hidden matter']);

    foreach ([SummarizeTicketPrompt::class, DraftReplyPrompt::class] as $prompt) {
        HarborServer::actingAs($this->agent)->prompt($prompt, ['ticket' => $hidden->id])
            ->assertHasErrors(["Ticket {$hidden->id} not found."])
            ->assertDontSee('Hidden matter');
    }
});

test('ticket prompts never resolve another workspace\'s ticket', function () {
    $other = Workspace::factory()->create(['subdomain' => 'globex']);
    $foreign = $other->execute(fn () => Ticket::factory()->create(['subject' => 'Globex secret']));
    $this->ticket->workspace->makeCurrent();

    HarborServer::actingAs($this->agent)->prompt(SummarizeTicketPrompt::class, ['ticket' => $foreign->id])
        ->assertHasErrors()
        ->assertDontSee('Globex secret');
});

test('triage_queue lists open unassigned tickets on visible desks', function () {
    $assigned = Ticket::factory()->create(['desk_id' => $this->desk->id, 'subject' => 'Already assigned']);
    $assigned->assignees()->attach($this->agent, ['workspace_id' => $assigned->workspace_id]);
    Ticket::factory()->create(['desk_id' => Desk::factory()->create(), 'subject' => 'Hidden matter']);

    HarborServer::actingAs($this->agent)->prompt(TriageQueuePrompt::class)
        ->assertOk()
        ->assertSee(['Printer on fire', 'Change nothing until the user confirms'])
        ->assertDontSee(['Already assigned', 'Hidden matter']);
});

test('triage_queue rejects a desk the user cannot see', function () {
    $other = Desk::factory()->create();

    HarborServer::actingAs($this->agent)->prompt(TriageQueuePrompt::class, ['desk' => $other->id])
        ->assertHasErrors(["Desk {$other->id} not found."]);
});
