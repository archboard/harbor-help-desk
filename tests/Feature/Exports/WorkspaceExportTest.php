<?php

use App\Actions\Exports\RequestWorkspaceExport;
use App\Enums\AuditAction;
use App\Enums\ExportStatus;
use App\Enums\ExportType;
use App\Enums\WorkspaceRole;
use App\Jobs\BuildExport;
use App\Models\Attachment;
use App\Models\AuditEvent;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\Export;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    Storage::fake(config('harbor.attachments.disk'));
    Notification::fake();

    ['workspace' => $this->workspace, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->admin = User::factory()->create(['password' => 'admin-password-hash-source']);
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);

    $this->other = Workspace::factory()->create(['subdomain' => 'other']);
    $this->workspace->makeCurrent();

    $this->api = 'http://acme.harbor.test/api/v1';
});

/**
 * Read an export's zip into a map of entry name to contents.
 *
 * @return array<string, string>
 */
function workspaceZip(Export $export): array
{
    $zip = new ZipArchive;
    $zip->open(Storage::disk(config('harbor.attachments.disk'))->path($export->path));
    $entries = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
    }

    return $entries;
}

/**
 * @return list<array<string, mixed>>
 */
function jsonLines(string $contents): array
{
    return array_map(fn ($line) => json_decode($line, true), array_filter(explode("\n", $contents)));
}

test('every table with a workspace_id column is exported or deliberately excluded', function () {
    $tenantTables = collect(Schema::getTables())
        ->pluck('name')
        ->filter(fn ($table) => Schema::hasColumn($table, 'workspace_id'))
        ->all();

    $listed = [...array_keys(BuildExport::WORKSPACE_TABLES), ...BuildExport::EXCLUDED_WORKSPACE_TABLES];

    expect(array_diff($tenantTables, $listed))->toBe([])
        ->and(array_diff(array_keys(BuildExport::WORKSPACE_TABLES), [...$tenantTables, 'workspaces', 'users']))->toBe([]);
});

test('the zip holds a manifest, every table as raw rows, and all attachment files', function () {
    $contact = Contact::factory()->create();
    $ticket = Ticket::factory()->create(['desk_id' => $this->desk->id, 'requester_id' => $contact->id, 'custom_fields' => ['plan' => 'pro']]);
    $internal = TicketComment::factory()->internal()->create(['ticket_id' => $ticket->id, 'body' => 'Internal note']);
    $disk = Storage::disk(config('harbor.attachments.disk'));
    $disk->put('att/internal.txt', 'internal file');
    $file = Attachment::factory()->create(['ticket_id' => $ticket->id, 'ticket_comment_id' => $internal->id, 'path' => 'att/internal.txt', 'filename' => 'internal.txt']);

    $export = (new RequestWorkspaceExport)->handle($this->admin);
    $export->refresh();
    $entries = workspaceZip($export);
    $manifest = json_decode($entries['manifest.json'], true);

    expect($export->type)->toBe(ExportType::WORKSPACE)
        ->and($export->subject_id)->toBeNull()
        ->and($export->status)->toBe(ExportStatus::COMPLETED)
        ->and($manifest['version'])->toBe(1)
        ->and($manifest['workspace_id'])->toBe($this->workspace->id)
        ->and($manifest['migration'])->toBe(DB::table('migrations')->orderByDesc('id')->value('migration'))
        ->and(array_keys($manifest['tables']))->toBe(array_keys(BuildExport::WORKSPACE_TABLES))
        ->and($entries["attachments/{$file->id}/internal.txt"])->toBe('internal file');

    foreach ($manifest['tables'] as $table => $count) {
        expect(jsonLines($entries["$table.jsonl"]))->toHaveCount($count, $table);
    }

    expect($manifest['tables'])->toMatchArray(['workspaces' => 1, 'users' => 2, 'workspace_user' => 2, 'contacts' => 1, 'tickets' => 1, 'ticket_comments' => 1, 'attachments' => 1, 'desks' => 1]);

    // Raw database values, not casted ones.
    $raw = DB::table('tickets')->where('id', $ticket->id)->first();
    $row = jsonLines($entries['tickets.jsonl'])[0];
    expect($row['created_at'])->toBe($raw->created_at)
        ->and($row['custom_fields'])->toBe($raw->custom_fields)
        ->and($row['workspace_id'])->toBe($this->workspace->id)
        ->and(jsonLines($entries['ticket_comments.jsonl'])[0]['is_internal'])->toBeTrue();
});

test('secrets and credentials are left out', function () {
    Mailbox::factory()->create(['push_secret' => 'push-secret-value', 'push_subscription_id' => 'sub-1']);
    Mailbox::query()->update(['settings' => ['imap' => ['password' => 'imap-password-value'], 'access_token' => 'oauth-token-value']]);
    $automation = Automation::factory()->create();
    DB::table('automations')->where('id', $automation->id)->update(['webhook_token_hash' => 'webhook-hash-value']);
    $this->admin->forceFill(['two_factor_secret' => 'two-factor-secret-value', 'remember_token' => 'remember-token-value'])->save();

    $export = (new RequestWorkspaceExport)->handle($this->admin);
    $entries = workspaceZip($export->refresh());
    $all = implode("\n", $entries);

    foreach (['push-secret-value', 'imap-password-value', 'oauth-token-value', 'webhook-hash-value', 'two-factor-secret-value', 'remember-token-value', $this->admin->getRawOriginal('password')] as $secret) {
        expect($all)->not->toContain($secret);
    }

    $mailbox = jsonLines($entries['mailboxes.jsonl'])[0];
    $user = jsonLines($entries['users.jsonl'])[0];

    expect($mailbox['settings'])->toBeNull()
        ->and($mailbox['push_secret'])->toBeNull()
        ->and($mailbox['address'])->not->toBeNull()
        ->and(jsonLines($entries['automations.jsonl'])[0]['webhook_token_hash'])->toBeNull()
        ->and(array_keys($user))->toBe(BuildExport::USER_COLUMNS)
        ->and($entries)->not->toHaveKeys(['notifications.jsonl', 'exports.jsonl']);
});

test('another workspace\'s rows and files are not exported', function () {
    $stranger = User::factory()->create(['email' => 'stranger@example.com']);

    $this->other->execute(function () use ($stranger) {
        $this->other->users()->attach($stranger, ['role' => WorkspaceRole::ADMIN]);
        $contact = Contact::factory()->create(['email' => 'leak@other.example']);
        $desk = $this->other->desks()->first();
        $ticket = Ticket::factory()->create(['desk_id' => $desk->id, 'requester_id' => $contact->id, 'subject' => 'Other workspace ticket']);
        Storage::disk(config('harbor.attachments.disk'))->put('att/other.txt', 'other file');
        Attachment::factory()->create(['ticket_id' => $ticket->id, 'path' => 'att/other.txt', 'filename' => 'other.txt']);
        Mailbox::factory()->create(['name' => 'Other mailbox']);
        Export::create(['type' => ExportType::WORKSPACE, 'status' => ExportStatus::PENDING, 'expires_at' => now()->addDay()]);
    });
    $this->workspace->makeCurrent();

    $export = (new RequestWorkspaceExport)->handle($this->admin);
    $entries = workspaceZip($export->refresh());
    $all = implode("\n", $entries);

    foreach (['stranger@example.com', 'leak@other.example', 'Other workspace ticket', 'other file', 'Other mailbox', $this->other->id, 'other.txt'] as $needle) {
        expect($all)->not->toContain($needle);
    }

    $manifest = json_decode($entries['manifest.json'], true);
    expect($manifest['tables'])->toMatchArray(['workspaces' => 1, 'users' => 2, 'contacts' => 0, 'tickets' => 0, 'attachments' => 0, 'mailboxes' => 0]);
});

test('requesting a workspace export records an audit event', function () {
    $export = (new RequestWorkspaceExport)->handle($this->admin);

    $event = AuditEvent::query()->where('action', AuditAction::WORKSPACE_EXPORT_REQUESTED)->sole();
    expect($event->actor_id)->toBe($this->admin->id)
        ->and($event->subject_id)->toBe($this->workspace->id)
        ->and($event->properties)->toBe(['export_id' => $export->id]);
});

test('admins request a workspace export on the web and the API, agents cannot', function () {
    $this->actingAs($this->agent)->post('http://acme.harbor.test/workspace/exports')->assertForbidden();
    actAs($this->agent, $this->workspace);
    $this->postJson("$this->api/workspace/export")->assertForbidden();
    expect(Export::count())->toBe(0);

    $this->actingAs($this->admin)->post('http://acme.harbor.test/workspace/exports')->assertRedirect();
    expect(Export::sole()->type)->toBe(ExportType::WORKSPACE);

    actAs($this->admin, $this->workspace);
    $this->postJson("$this->api/workspace/export")->assertCreated()->assertJsonPath('type', 'workspace')->assertJsonPath('subject_id', null);
    expect(Export::count())->toBe(2);

    expect(fn () => (new RequestWorkspaceExport)->handle($this->agent))->toThrow(AuthorizationException::class);
});
