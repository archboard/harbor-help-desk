<?php

use App\Actions\Mailboxes\DeleteMailbox;
use App\Data\Workspaces\WorkspaceSettings;
use App\Enums\MailboxDriver;
use App\Enums\WorkspaceRole;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->agent = User::factory()->create();
    $this->workspace->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);

    $this->mailbox = Mailbox::factory()->create(['driver' => MailboxDriver::IMAP]);
});

function storedSettings(Workspace $workspace): string
{
    return DB::table('workspaces')->where('id', $workspace->id)->value('settings');
}

test('the cast round-trips overrides, fills defaults and stores only differences', function () {
    expect(storedSettings($this->workspace))->toBe('{}')
        ->and($this->workspace->fresh()->settings)->toEqual(new WorkspaceSettings);

    $this->workspace->update(['settings' => ['mail_notification_mailbox_id' => $this->mailbox->id]]);

    $settings = $this->workspace->fresh()->settings;
    expect($settings->mailNotificationMailboxId)->toBe($this->mailbox->id);

    $this->workspace->update(['settings' => $settings]);
    expect($this->workspace->fresh()->settings->toArray())->toBe($settings->toArray());

    $this->workspace->update(['settings' => ['mail_notification_mailbox_id' => null]]);
    expect(storedSettings($this->workspace))->toBe('{}');
});

test('unknown stored keys are ignored on read and dropped on write', function () {
    DB::table('workspaces')->where('id', $this->workspace->id)->update(['settings' => '{"gone":1}']);

    $workspace = $this->workspace->fresh();
    expect($workspace->settings)->toEqual(new WorkspaceSettings);

    $workspace->update(['settings' => $workspace->settings]);
    expect(storedSettings($workspace))->toBe('{}');
});

test('admins update settings, partial updates merge and null resets', function () {
    $this->actingAs($this->admin)
        ->patch(route('workspace.settings.update'), ['mail_notification_mailbox_id' => $this->mailbox->id])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->workspace->fresh()->settings->mailNotificationMailboxId)->toBe($this->mailbox->id);

    $this->actingAs($this->admin)->patch(route('workspace.settings.update'), [])->assertSessionHasNoErrors();
    expect($this->workspace->fresh()->settings->mailNotificationMailboxId)->toBe($this->mailbox->id);

    $this->actingAs($this->admin)->patch(route('workspace.settings.update'), ['mail_notification_mailbox_id' => null]);
    expect($this->workspace->fresh()->settings->mailNotificationMailboxId)->toBeNull();
});

test('unknown keys are rejected on write', function () {
    $this->actingAs($this->admin)
        ->patch(route('workspace.settings.update'), ['nope' => 1])
        ->assertSessionHasErrors('nope');
});

test('a mailbox from another workspace is rejected', function () {
    $other = Workspace::factory()->create();
    $foreign = $other->execute(fn () => Mailbox::factory()->create(['driver' => MailboxDriver::IMAP]));

    $this->actingAs($this->admin)
        ->patch(route('workspace.settings.update'), ['mail_notification_mailbox_id' => $foreign->id])
        ->assertSessionHasErrors('mail_notification_mailbox_id');

    expect($this->workspace->fresh()->settings->mailNotificationMailboxId)->toBeNull();
});

test('agents neither see nor update settings', function () {
    $this->actingAs($this->agent)
        ->get(route('desks.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('workspace.settings'));

    $this->actingAs($this->agent)
        ->patch(route('workspace.settings.update'), ['mail_notification_mailbox_id' => $this->mailbox->id])
        ->assertForbidden();

    expect($this->workspace->fresh()->settings->mailNotificationMailboxId)->toBeNull();
});

test('deleting the mailbox clears the reference', function () {
    $this->workspace->update(['settings' => ['mail_notification_mailbox_id' => $this->mailbox->id]]);

    app(DeleteMailbox::class)->handle($this->admin, $this->mailbox);

    expect($this->workspace->fresh()->settings->mailNotificationMailboxId)->toBeNull();
});
