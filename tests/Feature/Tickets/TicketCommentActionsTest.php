<?php

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\DeleteTicketComment;
use App\Actions\Tickets\UpdateTicketComment;
use App\Enums\DeskRole;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

function onDesk(Workspace $workspace, Desk $desk, DeskRole $role = DeskRole::AGENT): User
{
    $user = User::factory()->create(['name' => 'Jane Doe']);
    $workspace->users()->attach($user, ['role' => WorkspaceRole::AGENT]);
    $desk->users()->attach($user, ['role' => $role, 'workspace_id' => $workspace->id]);

    return $user;
}

test('agents reply or add internal notes and each is logged', function () {
    $ticket = Ticket::factory()->create();
    $agent = onDesk($this->workspace, $ticket->desk);

    $reply = app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'On it.']);
    $note = app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'Probably the toner.', 'is_internal' => true]);

    expect($ticket->comments()->get()->map->only('id', 'body', 'is_internal')->all())->toBe([
        ['id' => $reply->id, 'body' => 'On it.', 'is_internal' => false],
        ['id' => $note->id, 'body' => 'Probably the toner.', 'is_internal' => true],
    ]);
    expect($reply->author->is($agent))->toBeTrue();
    expect($ticket->events()->get()->map->description()->all())->toBe([
        'Jane Doe replied.',
        'Jane Doe added an internal note.',
    ]);
});

test('agents off the desk cannot comment', function () {
    $ticket = Ticket::factory()->create();
    $outsider = onDesk($this->workspace, Desk::factory()->create());

    expect(fn () => app(AddTicketComment::class)->handle($outsider, $ticket, ['body' => 'Hi']))
        ->toThrow(AuthorizationException::class);
});

test('authors can edit their comment and the previous body stays in the audit trail', function () {
    $ticket = Ticket::factory()->create();
    $author = onDesk($this->workspace, $ticket->desk);
    $comment = TicketComment::factory()->for($ticket)->for($author, 'author')->create(['body' => 'Frist']);

    $this->freezeSecond();
    app(UpdateTicketComment::class)->handle($author, $comment, ['body' => 'First']);

    expect($comment->fresh())
        ->body->toBe('First')
        ->edited_at->toEqual(now());
    expect($ticket->events()->sole())
        ->type->toBe(TicketEventType::COMMENT_EDITED)
        ->properties->toEqual(['comment_id' => $comment->id, 'from' => 'Frist']);
});

test('agents cannot edit or delete someone else comment', function (string $action, array $arguments) {
    $ticket = Ticket::factory()->create();
    $comment = TicketComment::factory()->for($ticket)->for(onDesk($this->workspace, $ticket->desk), 'author')->create();
    $agent = onDesk($this->workspace, $ticket->desk);

    expect(fn () => app($action)->handle($agent, $comment, ...$arguments))->toThrow(AuthorizationException::class);
})->with([
    'edit' => [UpdateTicketComment::class, [['body' => 'Changed']]],
    'delete' => [DeleteTicketComment::class, []],
]);

test('desk leads can delete any comment and it is soft-deleted', function () {
    $ticket = Ticket::factory()->create();
    $comment = TicketComment::factory()->for($ticket)->for(onDesk($this->workspace, $ticket->desk), 'author')->create();

    app(DeleteTicketComment::class)->handle(onDesk($this->workspace, $ticket->desk, DeskRole::LEAD), $comment);

    expect($ticket->comments()->count())->toBe(0);
    expect($comment->fresh()->trashed())->toBeTrue();
    expect($ticket->events()->sole()->description())->toBe('Jane Doe deleted a comment.');
});

test('workspace b cannot read workspace a comments', function () {
    $commentA = TicketComment::factory()->create();
    Workspace::factory()->create()->makeCurrent();

    expect(TicketComment::find($commentA->id))->toBeNull();
});
