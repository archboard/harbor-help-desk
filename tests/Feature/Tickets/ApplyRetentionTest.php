<?php

use App\Actions\Tickets\ApplyRetention;
use App\Enums\AuditAction;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Enums\WorkspaceRole;
use App\Models\Attachment;
use App\Models\AuditEvent;
use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketMetric;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    Storage::fake(config('harbor.attachments.disk'));
    $this->travelTo(now()->startOfSecond());

    ['workspace' => $this->workspace, 'desk' => $this->desk] = acmeWithAgent();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
});

/**
 * A ticket with the given status that was resolved this many days ago.
 */
function resolvedTicket(int $daysAgo, TicketStatus $status = TicketStatus::SOLVED): Ticket
{
    $ticket = Ticket::factory()->create(['desk_id' => test()->desk->id, 'status' => $status]);
    TicketMetric::factory()->create(['ticket_id' => $ticket->id, 'resolved_at' => now()->subDays($daysAgo)]);

    return $ticket;
}

test('resolved tickets older than the setting are trashed with a system DELETED event', function () {
    $this->workspace->update(['settings' => ['retention_resolved_ticket_days' => 10]]);

    $exactly = resolvedTicket(10);
    $older = resolvedTicket(11, TicketStatus::CLOSED);
    $recent = resolvedTicket(9);
    $reopened = resolvedTicket(30, TicketStatus::OPEN);
    $never = Ticket::factory()->create(['desk_id' => $this->desk->id, 'status' => TicketStatus::SOLVED]);

    expect(app(ApplyRetention::class)->handle())->toBe(['trashed' => 1, 'purged' => 0])
        ->and(Ticket::onlyTrashed()->pluck('id')->all())->toBe([$older->id])
        ->and(Ticket::find($exactly->id))->not->toBeNull()
        ->and(Ticket::find($recent->id))->not->toBeNull()
        ->and(Ticket::find($reopened->id))->not->toBeNull()
        ->and(Ticket::find($never->id))->not->toBeNull();

    $event = TicketEvent::query()->where('ticket_id', $older->id)->where('type', TicketEventType::DELETED)->sole();
    expect($event->actor_type)->toBeNull()->and($event->actorName())->toBe('Harbor');
});

test('a null setting keeps resolved tickets forever', function () {
    resolvedTicket(5000);

    expect(app(ApplyRetention::class)->handle())->toBe(['trashed' => 0, 'purged' => 0])
        ->and(Ticket::count())->toBe(1);
});

test('trashed tickets are purged past the trash window with their files and rows', function () {
    $disk = Storage::disk(config('harbor.attachments.disk'));
    $disk->put('att/old.txt', 'x');
    $disk->put('att/new.txt', 'y');

    $old = Ticket::factory()->create(['desk_id' => $this->desk->id]);
    $file = Attachment::factory()->create(['ticket_id' => $old->id, 'path' => 'att/old.txt']);
    $old->recordEvent(TicketEventType::DELETED, null);
    $old->delete();
    Ticket::withTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(31)]);

    $edge = Ticket::factory()->create(['desk_id' => $this->desk->id]);
    Attachment::factory()->create(['ticket_id' => $edge->id, 'path' => 'att/new.txt']);
    $edge->delete();
    Ticket::withTrashed()->whereKey($edge->id)->update(['deleted_at' => now()->subDays(30)]);

    expect(app(ApplyRetention::class)->handle())->toBe(['trashed' => 0, 'purged' => 1])
        ->and(Ticket::withTrashed()->find($old->id))->toBeNull()
        ->and(Attachment::find($file->id))->toBeNull()
        ->and(TicketEvent::query()->where('ticket_id', $old->id)->exists())->toBeFalse()
        ->and($disk->exists('att/old.txt'))->toBeFalse()
        ->and(Ticket::withTrashed()->find($edge->id))->not->toBeNull()
        ->and($disk->exists('att/new.txt'))->toBeTrue();
});

test('each workspace uses its own settings and the command runs across workspaces', function () {
    $this->workspace->update(['settings' => ['retention_resolved_ticket_days' => 5]]);
    $mine = resolvedTicket(20);

    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $other->makeCurrent();
    $theirs = resolvedTicket(20);
    $theirTrashed = Ticket::factory()->create(['desk_id' => $theirs->desk_id]);
    $theirTrashed->delete();
    Ticket::withTrashed()->whereKey($theirTrashed->id)->update(['deleted_at' => now()->subDays(20)]);
    $other->update(['settings' => ['retention_trash_days' => 10]]);

    $this->artisan('tickets:apply-retention')->assertSuccessful();

    $this->workspace->makeCurrent();
    expect(Ticket::onlyTrashed()->pluck('id')->all())->toBe([$mine->id]);

    $other->makeCurrent();
    expect(Ticket::find($theirs->id))->not->toBeNull()
        ->and(Ticket::withTrashed()->find($theirTrashed->id))->toBeNull();
});

test('admins save retention settings from the data and privacy page, audited, with bounds', function () {
    $this->actingAs($this->admin)->get('http://acme.harbor.test/workspace/data-privacy')
        ->assertInertia(fn (Assert $page) => $page
            ->where('workspace.settings.retention_resolved_ticket_days', null)
            ->where('workspace.settings.retention_trash_days', 30));

    $this->actingAs($this->admin)->patch(route('workspace.settings.update'), ['retention_resolved_ticket_days' => 90, 'retention_trash_days' => 7])
        ->assertSessionHasNoErrors();

    expect($this->workspace->fresh()->settings)
        ->retentionResolvedTicketDays->toBe(90)
        ->retentionTrashDays->toBe(7)
        ->and(AuditEvent::query()->where('action', AuditAction::WORKSPACE_SETTINGS_UPDATED)->latest('id')->first()->properties)
        ->toBe(['changed' => ['retention_resolved_ticket_days', 'retention_trash_days']]);

    $this->actingAs($this->admin)->patch(route('workspace.settings.update'), ['retention_resolved_ticket_days' => null]);
    expect($this->workspace->fresh()->settings->retentionResolvedTicketDays)->toBeNull();

    foreach ([['retention_resolved_ticket_days' => 0], ['retention_resolved_ticket_days' => 3651], ['retention_trash_days' => 0], ['retention_trash_days' => 366]] as $input) {
        $this->actingAs($this->admin)->patch(route('workspace.settings.update'), $input)->assertSessionHasErrors(array_key_first($input));
    }
});

test('the api validates and saves retention settings', function () {
    actAs($this->admin, $this->workspace);

    $this->patchJson('http://acme.harbor.test/api/v1/workspace/settings', ['retention_trash_days' => 0])
        ->assertUnprocessable()->assertJsonValidationErrors('retention_trash_days');

    $this->patchJson('http://acme.harbor.test/api/v1/workspace/settings', ['retention_resolved_ticket_days' => 30])
        ->assertOk()->assertJsonPath('settings.retention_resolved_ticket_days', 30);
});
