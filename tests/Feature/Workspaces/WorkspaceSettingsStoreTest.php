<?php

use App\Actions\Mailboxes\DeleteMailbox;
use App\Data\Workspaces\WorkspaceSettings;
use App\Enums\MailboxDriver;
use App\Enums\PortalAccess;
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

test('the new ticket limit must be between 1 and 1000', function () {
    foreach ([0, 1001] as $limit) {
        $this->actingAs($this->admin)
            ->patch(route('workspace.settings.update'), ['mail_new_tickets_per_sender_per_hour' => $limit])
            ->assertSessionHasErrors('mail_new_tickets_per_sender_per_hour');
    }

    $this->actingAs($this->admin)
        ->patch(route('workspace.settings.update'), ['mail_new_tickets_per_sender_per_hour' => 50])
        ->assertSessionHasNoErrors();

    expect($this->workspace->fresh()->settings->mailNewTicketsPerSenderPerHour)->toBe(50);
});

test('the portal and survey settings update, and the access must be a known value', function () {
    $this->actingAs($this->admin)
        ->patch(route('workspace.settings.update'), ['portal_enabled' => false, 'portal_access' => 'contacts', 'surveys_enabled' => true])
        ->assertSessionHasNoErrors();

    $settings = $this->workspace->fresh()->settings;
    expect($settings->portalEnabled)->toBeFalse()
        ->and($settings->portalAccess)->toBe(PortalAccess::CONTACTS)
        ->and($settings->surveysEnabled)->toBeTrue();

    $this->actingAs($this->admin)
        ->patch(route('workspace.settings.update'), ['portal_access' => 'everyone'])
        ->assertSessionHasErrors('portal_access');
});

test('the portal settings page is for admins only', function () {
    $this->actingAs($this->admin)->get(route('workspace.portal.edit'))
        ->assertInertia(fn (Assert $page) => $page->component('workspace/Portal')->where('accessOptions.anyone', 'Anyone with an email address'));

    $this->actingAs($this->agent)->get(route('workspace.portal.edit'))->assertForbidden();
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

test('attachment limits above the platform cap or with bad extensions are rejected', function (array $input, string $key) {
    config(['harbor.attachments.max_size_kb' => 1000]);

    $this->actingAs($this->admin)
        ->patch(route('workspace.settings.update'), $input)
        ->assertSessionHasErrors($key);
})->with([
    'over the cap' => [['attachments_max_size_kb' => 1001], 'attachments_max_size_kb'],
    'not positive' => [['attachments_max_size_kb' => 0], 'attachments_max_size_kb'],
    'dotted' => [['attachments_blocked_extensions' => ['.exe']], 'attachments_blocked_extensions.0'],
    'duplicate' => [['attachments_blocked_extensions' => ['exe', 'exe']], 'attachments_blocked_extensions.0'],
]);
