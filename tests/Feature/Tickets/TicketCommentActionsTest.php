<?php

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\DeleteTicketComment;
use App\Actions\Tickets\UpdateTicketComment;
use App\Enums\DeskRole;
use App\Enums\TicketEventType;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

test('agents reply or add internal notes and each is logged', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);

    $reply = app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'On it.']);
    $note = app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'Probably the toner.', 'is_internal' => true]);

    expect($ticket->comments()->get()->map->only('id', 'body', 'is_internal')->all())->toBe([
        ['id' => $reply->id, 'body' => 'On it.', 'is_internal' => false],
        ['id' => $note->id, 'body' => 'Probably the toner.', 'is_internal' => true],
    ]);
    expect($reply->author->is($agent))->toBeTrue();
    expect($ticket->events()->with('actor')->get()->map->description()->all())->toBe([
        'Jane Doe replied.',
        'Jane Doe added an internal note.',
    ]);
});

test('agents off the desk cannot comment', function () {
    $ticket = Ticket::factory()->create();
    $outsider = deskMember($this->workspace, Desk::factory()->create());

    expect(fn () => app(AddTicketComment::class)->handle($outsider, $ticket, ['body' => 'Hi']))
        ->toThrow(AuthorizationException::class);
});

test('a contact can reply by email without a desk policy but cannot add internal notes', function () {
    $ticket = Ticket::factory()->create();

    $reply = app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => 'Still broken.', 'message_id' => '<abc@mail.example.com>']);

    expect($reply->author->is($ticket->requester))->toBeTrue();
    expect($reply->message_id)->toBe('<abc@mail.example.com>');
    expect(fn () => app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => 'Psst', 'is_internal' => true]))
        ->toThrow(ValidationException::class);
});

test('agents cannot set a message id', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);

    expect(fn () => app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'Hi', 'message_id' => '<abc@example.com>']))
        ->toThrow(ValidationException::class);
});

test('authors can edit their comment and the previous body stays in the audit trail', function () {
    $ticket = Ticket::factory()->create();
    $author = deskMember($this->workspace, $ticket->desk);
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
    $comment = TicketComment::factory()->for($ticket)->for(deskMember($this->workspace, $ticket->desk), 'author')->create();
    $agent = deskMember($this->workspace, $ticket->desk);

    expect(fn () => app($action)->handle($agent, $comment, ...$arguments))->toThrow(AuthorizationException::class);
})->with([
    'edit' => [UpdateTicketComment::class, [['body' => 'Changed']]],
    'delete' => [DeleteTicketComment::class, []],
]);

test('desk leads can delete any comment and it is soft-deleted', function () {
    $ticket = Ticket::factory()->create();
    $comment = TicketComment::factory()->for($ticket)->for(deskMember($this->workspace, $ticket->desk), 'author')->create();

    app(DeleteTicketComment::class)->handle(deskMember($this->workspace, $ticket->desk, DeskRole::LEAD), $comment);

    expect($ticket->comments()->count())->toBe(0);
    expect($comment->fresh()->trashed())->toBeTrue();
    expect($ticket->events()->with('actor')->sole()->description())->toBe('Jane Doe deleted a comment.');
});

test('workspace b cannot read workspace a comments', function () {
    $commentA = TicketComment::factory()->create();
    Workspace::factory()->create()->makeCurrent();

    expect(TicketComment::find($commentA->id))->toBeNull();
});

test('an internal note stores its mentions and rejects non-members, repeats, public replies and contacts', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);
    $colleague = deskMember($this->workspace, $ticket->desk);
    $outsider = deskMember($this->workspace, Desk::factory()->create());
    $add = fn ($actor, array $input) => fn () => app(AddTicketComment::class)->handle($actor, $ticket, ['body' => 'Hi', ...$input]);

    $add($agent, ['is_internal' => true, 'mentioned_user_ids' => [$colleague->id]])();
    expect($ticket->events()->where('type', TicketEventType::INTERNAL_NOTE_ADDED)->sole()->properties['mentioned_user_ids'])->toBe([$colleague->id]);

    expect($add($agent, ['is_internal' => true, 'mentioned_user_ids' => [$outsider->id]]))->toThrow(ValidationException::class)
        ->and($add($agent, ['is_internal' => true, 'mentioned_user_ids' => [$colleague->id, $colleague->id]]))->toThrow(ValidationException::class)
        ->and($add($agent, ['mentioned_user_ids' => [$colleague->id]]))->toThrow(ValidationException::class)
        ->and($add($ticket->requester, ['mentioned_user_ids' => [$colleague->id]]))->toThrow(ValidationException::class);
});

test('a stale reply is blocked when someone else commented after the last seen comment', function (string $who) {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);
    $seen = app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => 'Hello?']);
    $other = $who === 'contact' ? $ticket->requester : deskMember($this->workspace, $ticket->desk);
    app(AddTicketComment::class)->handle($other, $ticket, ['body' => 'Anyone there?']);

    try {
        app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'Hi', 'last_seen_comment_id' => $seen->id]);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $e) {
        expect($e->errors()['last_seen_comment_id'])->toBe([$other->name.' replied while you were writing.']);
    }

    expect($ticket->comments()->count())->toBe(2);
})->with(['another agent' => 'agent', 'the contact' => 'contact']);

test('a stale reply guard with a null id blocks on any comment from someone else', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);
    app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => 'Hello?']);

    expect(fn () => app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'Hi', 'last_seen_comment_id' => null]))
        ->toThrow(ValidationException::class);
});

test('the stale reply guard ignores the actor own comments and an omitted field', function () {
    $ticket = Ticket::factory()->create();
    $agent = deskMember($this->workspace, $ticket->desk);
    $mine = app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'First.']);
    app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'Second.', 'last_seen_comment_id' => null]);
    app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'Third.', 'last_seen_comment_id' => $mine->id]);

    app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => 'Thanks']);
    app(AddTicketComment::class)->handle($agent, $ticket, ['body' => 'Fourth.']);

    expect($ticket->comments()->count())->toBe(5);
});

test('contacts cannot send last_seen_comment_id', function () {
    $ticket = Ticket::factory()->create();

    expect(fn () => app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => 'Hi', 'last_seen_comment_id' => 'cmt_x']))
        ->toThrow(ValidationException::class);
});
