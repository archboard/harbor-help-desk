<?php

use App\Actions\Exports\RequestWorkspaceExport;
use App\Actions\Workspaces\ImportWorkspace;
use App\Enums\AuditAction;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Jobs\BuildExport;
use App\Models\Attachment;
use App\Models\AuditEvent;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\AutomationTicketMatch;
use App\Models\BusinessSchedule;
use App\Models\Contact;
use App\Models\CustomField;
use App\Models\Macro;
use App\Models\Mailbox;
use App\Models\SatisfactionRating;
use App\Models\SavedView;
use App\Models\SlaPolicy;
use App\Models\Tag;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketEvent;
use App\Models\TicketMetric;
use App\Models\TicketReminder;
use App\Models\TicketSlaTarget;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceImportFinishedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    Storage::fake(config('harbor.attachments.disk'));
    Notification::fake();

    ['workspace' => $this->source, 'desk' => $this->desk, 'agent' => $this->agent] = acmeWithAgent();
    $this->admin = User::factory()->create();
    $this->source->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->source->makeCurrent();
    $this->api = 'http://fresh.harbor.test/api/v1';
});

/**
 * Insert a row of a table the factories don't cover, with a new id of the model's kind.
 *
 * @param  class-string<Model>  $model
 * @param  array<string, mixed>  $attributes
 */
function insertRow(string $model, array $attributes): string
{
    $id = (new $model)->newUniqueId();
    $table = (new $model)->getTable();

    DB::table($table)->insert(['id' => $id, 'workspace_id' => Workspace::current()->id, 'created_at' => now(), ...$attributes]);

    return $id;
}

/**
 * Fill the current workspace: every table of the export gets at least one row, and the rows point at the agent.
 */
function seedWorkspace(): void
{
    $workspace = Workspace::current();
    $desk = $workspace->desks()->sole();
    $agent = $workspace->users()->wherePivot('role', WorkspaceRole::AGENT)->sole();
    $disk = Storage::disk(config('harbor.attachments.disk'));

    $schedule = BusinessSchedule::factory()->create();
    $sla = SlaPolicy::factory()->create(['business_schedule_id' => $schedule->id]);
    $desk->update(['sla_policy_id' => $sla->id]);
    $field = CustomField::factory()->create();
    DB::table('custom_field_desk')->insert(['workspace_id' => $workspace->id, 'custom_field_id' => $field->id, 'desk_id' => $desk->id]);
    $tag = Tag::factory()->create();
    $contact = Contact::factory()->create();
    $mailbox = Mailbox::factory()->create(['default_desk_id' => $desk->id, 'push_secret' => 'push-secret', 'push_subscription_id' => 'sub-1', 'polled_at' => now()]);
    $automation = Automation::factory()->triggeredBy('webhook', 'add_note', ['body' => 'Hi'])->create(['definition' => ['trigger' => ['type' => 'webhook', 'config' => []], 'steps' => [['id' => 'one', 'type' => 'action', 'action' => 'assign', 'config' => ['assignee_id' => $agent->id]]]]]);
    DB::table('automations')->where('id', $automation->id)->update(['webhook_token_hash' => hash('sha256', 'old-token'), 'created_by' => $agent->id]);
    Macro::factory()->personal($agent)->create();
    SavedView::factory()->create(['user_id' => $agent->id, 'filters' => ['assignee_id' => $agent->id]]);

    $ticket = Ticket::factory()->create(['desk_id' => $desk->id, 'requester_id' => $contact->id, 'sla_policy_id' => $sla->id, 'mailbox_id' => $mailbox->id]);
    Ticket::factory()->count(2)->create(['desk_id' => $desk->id, 'requester_id' => $contact->id]);
    DB::table('ticket_user')->insert(['workspace_id' => $workspace->id, 'ticket_id' => $ticket->id, 'user_id' => $agent->id]);
    DB::table('tag_ticket')->insert(['workspace_id' => $workspace->id, 'ticket_id' => $ticket->id, 'tag_id' => $tag->id]);
    $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id, 'author_id' => $agent->id, 'body' => "Thanks <@{$agent->id}>"]);
    $disk->put("{$workspace->id}/tickets/{$ticket->id}/file.txt", 'attachment contents');
    Attachment::factory()->create(['ticket_id' => $ticket->id, 'ticket_comment_id' => $comment->id, 'uploader_id' => $agent->id, 'path' => "{$workspace->id}/tickets/{$ticket->id}/file.txt", 'filename' => 'file.txt']);
    TicketReminder::factory()->create(['ticket_id' => $ticket->id, 'created_by' => $agent->id]);
    SatisfactionRating::factory()->create(['ticket_id' => $ticket->id]);
    TicketMetric::factory()->create(['ticket_id' => $ticket->id, 'first_responder_id' => $agent->id]);
    insertRow(TicketSlaTarget::class, ['ticket_id' => $ticket->id, 'metric' => 'resolution', 'target_minutes' => 60, 'started_at' => now(), 'updated_at' => now()]);

    $runId = insertRow(AutomationRun::class, ['automation_id' => $automation->id, 'ticket_id' => $ticket->id, 'status' => 'completed', 'definition' => '{}', 'log' => '[]', 'state' => '{}', 'updated_at' => now()]);
    insertRow(TicketEvent::class, ['ticket_id' => $ticket->id, 'actor_type' => 'user', 'actor_id' => $agent->id, 'type' => TicketEventType::CREATED->value, 'properties' => json_encode(['assignee_id' => $agent->id]), 'automation_run_id' => $runId]);
    insertRow(AutomationTicketMatch::class, ['automation_id' => $automation->id, 'ticket_id' => $ticket->id, 'fired_at' => now(), 'updated_at' => now()]);
}

/**
 * Export the workspace and keep the zip as an upload.
 */
function exportUpload(User $admin, ?Closure $tamper = null): UploadedFile
{
    $export = (new RequestWorkspaceExport)->handle($admin)->refresh();
    $local = tempnam(sys_get_temp_dir(), 'import-test');
    file_put_contents($local, Storage::disk(config('harbor.attachments.disk'))->get($export->path));

    if ($tamper) {
        $zip = new ZipArchive;
        $zip->open($local);
        $tamper($zip);
        $zip->close();
    }

    return new UploadedFile($local, 'export.zip', 'application/zip', null, true);
}

/**
 * Edit the manifest of an export zip.
 *
 * @param  array<string, mixed>  $changes
 */
function manifestWith(array $changes): Closure
{
    return function (ZipArchive $zip) use ($changes): void {
        $manifest = [...json_decode($zip->getFromName('manifest.json'), true), ...$changes];
        $zip->addFromString('manifest.json', json_encode($manifest));
    };
}

/**
 * @return array<string, int>
 */
function tableCounts(string $workspaceId): array
{
    return collect(BuildExport::WORKSPACE_TABLES)
        ->except(['workspaces', 'users'])
        ->mapWithKeys(fn ($keys, $table) => [$table => DB::table($table)->where('workspace_id', $workspaceId)->count()])
        ->all();
}

/**
 * A new workspace with the admin in it, made current.
 */
function freshWorkspace(User $admin, string $subdomain = 'fresh'): Workspace
{
    $workspace = Workspace::factory()->create(['subdomain' => $subdomain])->makeCurrent();
    $workspace->users()->attach($admin, ['role' => WorkspaceRole::ADMIN]);

    return $workspace;
}

/**
 * Export the source workspace, then delete it so its ids are free.
 */
function exportAndDeleteSource(User $admin): UploadedFile
{
    $upload = exportUpload($admin);
    DB::table('exports')->delete();
    Workspace::current()->delete();

    return $upload;
}

test('an export imports into a fresh workspace with the same rows, ids and files', function () {
    seedWorkspace();
    $upload = exportUpload($this->admin);
    $manifest = json_decode((new ZipArchive)->open($upload->getRealPath()) === true ? file_get_contents('zip://'.$upload->getRealPath().'#manifest.json') : '', true);
    $ids = collect(BuildExport::WORKSPACE_TABLES)->except(['workspaces', 'users', 'workspace_user', 'desk_user', 'custom_field_desk', 'ticket_user', 'tag_ticket', 'audit_events'])
        ->map(fn ($keys, $table) => DB::table($table)->where('workspace_id', $this->source->id)->orderBy('id')->pluck('id')->all());
    $sourceFile = DB::table('attachments')->value('path');
    $auditId = DB::table('audit_events')->value('id');
    DB::table('exports')->delete();
    $this->source->delete();
    $target = freshWorkspace($this->admin);

    (new ImportWorkspace)->handle($this->admin, ['file' => $upload]);

    $expected = $manifest['tables'];
    $counts = tableCounts($target->id);

    expect($counts['audit_events'])->toBe($expected['audit_events'] + 2)
        ->and(Arr::except($counts, 'audit_events'))->toBe(Arr::except(Arr::except($expected, ['workspaces', 'users']), 'audit_events'))
        ->and($ids->map(fn ($ids, $table) => DB::table($table)->where('workspace_id', $target->id)->orderBy('id')->pluck('id')->all())->all())->toBe($ids->all())
        ->and(DB::table('audit_events')->where('workspace_id', $target->id)->pluck('id')->all())->toContain($auditId);

    $attachment = Attachment::sole();
    expect($attachment->path)->toBe("{$target->id}/tickets/{$attachment->ticket_id}/{$attachment->id}/file.txt")
        ->and(Storage::disk(config('harbor.attachments.disk'))->get($attachment->path))->toBe('attachment contents')
        ->and($attachment->path)->not->toBe($sourceFile)
        ->and(DB::table('ticket_events')->value('automation_run_id'))->toBe(AutomationRun::sole()->id)
        ->and(AuditEvent::query()->where('action', AuditAction::WORKSPACE_IMPORT_REQUESTED)->exists())->toBeTrue()
        ->and(AuditEvent::query()->where('action', AuditAction::WORKSPACE_IMPORTED)->sole()->actor_id)->toBe($this->admin->id)
        ->and(Storage::disk(config('harbor.attachments.disk'))->allFiles("{$target->id}/imports"))->toBe([]);

    Notification::assertSentTo($this->admin, WorkspaceImportFinishedNotification::class, fn ($notification) => $notification->succeeded);

    $highest = Ticket::query()->max('number');
    $next = Ticket::factory()->create(['desk_id' => $target->desks()->first()->id, 'requester_id' => Contact::query()->first()->id]);
    expect($next->number)->toBe($highest + 1);
});

test('the workspace must have no tickets or contacts, trashed ones included', function (string $kind) {
    $upload = exportUpload($this->admin);
    $target = freshWorkspace($this->admin);
    $desk = $target->desks()->sole();

    match ($kind) {
        'ticket' => Ticket::factory()->create(['desk_id' => $desk->id]),
        'trashed ticket' => Ticket::factory()->create(['desk_id' => $desk->id])->delete(),
        'contact' => Contact::factory()->create(),
    };
    $before = tableCounts($target->id);

    expect(fn () => (new ImportWorkspace)->handle($this->admin, ['file' => $upload]))->toThrow(ValidationException::class)
        ->and(tableCounts($target->id))->toBe($before)
        ->and(Storage::disk(config('harbor.attachments.disk'))->allFiles("{$target->id}/imports"))->toBe([]);
    Notification::assertNotSentTo($this->admin, WorkspaceImportFinishedNotification::class);
})->with(['ticket', 'trashed ticket', 'contact']);

test('exports with a wrong version, a different migration or a broken zip are rejected', function (string $case) {
    $upload = match ($case) {
        'version' => exportUpload($this->admin, manifestWith(['version' => 2])),
        'migration' => exportUpload($this->admin, manifestWith(['migration' => '2000_01_01_000000_old'])),
        'no manifest' => exportUpload($this->admin, fn (ZipArchive $zip) => $zip->deleteName('manifest.json')),
        'missing table' => exportUpload($this->admin, fn (ZipArchive $zip) => $zip->deleteName('tickets.jsonl')),
        'not a zip' => UploadedFile::fake()->createWithContent('export.zip', 'this is not a zip'),
    };
    $target = freshWorkspace($this->admin);

    try {
        (new ImportWorkspace)->handle($this->admin, ['file' => $upload]);
        $this->fail('The import was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('file');
    }

    expect(Storage::disk(config('harbor.attachments.disk'))->allFiles("{$target->id}/imports"))->toBe([])
        ->and(AuditEvent::query()->where('action', AuditAction::WORKSPACE_IMPORT_REQUESTED)->exists())->toBeFalse();
})->with(['version', 'migration', 'no manifest', 'missing table', 'not a zip']);

test('an id collision fails the import and rolls back every row and file', function (string $case) {
    seedWorkspace();
    $upload = exportUpload($this->admin);

    if ($case === 'late') {
        // Only the last table collides, so every file has been copied by then.
        $auditId = DB::table('audit_events')->orderByDesc('id')->value('id');
        $other = Workspace::factory()->create(['subdomain' => 'other']);
        DB::table('exports')->delete();
        DB::table('audit_events')->where('workspace_id', $this->source->id)->where('id', '!=', $auditId)->delete();
        DB::table('audit_events')->where('id', $auditId)->update(['workspace_id' => $other->id]);
        $this->source->delete();
    }

    $target = freshWorkspace($this->admin);
    $before = tableCounts($target->id);
    $desk = $target->desks()->sole();
    $disk = Storage::disk(config('harbor.attachments.disk'));
    $userCount = User::count();

    (new ImportWorkspace)->handle($this->admin, ['file' => $upload]);

    expect(tableCounts($target->id))->toBe([...$before, 'audit_events' => $before['audit_events'] + 2])
        ->and($target->desks()->pluck('id')->all())->toBe([$desk->id])
        ->and(User::count())->toBe($userCount)
        ->and($disk->allFiles("{$target->id}/tickets"))->toBe([])
        ->and($disk->allFiles("{$target->id}/imports"))->toBe([])
        ->and(AuditEvent::query()->where('action', AuditAction::WORKSPACE_IMPORT_FAILED)->sole()->properties)->toBe(['exception' => UniqueConstraintViolationException::class])
        ->and(AuditEvent::query()->where('action', AuditAction::WORKSPACE_IMPORTED)->exists())->toBeFalse();

    Notification::assertSentTo($this->admin, WorkspaceImportFinishedNotification::class, fn ($notification) => ! $notification->succeeded);
})->with(['source still exists' => 'exists', 'last table collides' => 'late']);

test('users are matched by email and every reference to them is remapped', function () {
    seedWorkspace();
    $oldId = $this->agent->id;
    $newcomer = deskMember($this->source, $this->desk, attributes: ['email' => 'newcomer@example.com']);
    $upload = exportAndDeleteSource($this->admin);

    $localId = (new User)->newUniqueId();
    DB::table('users')->where('id', $oldId)->update(['id' => $localId, 'email' => strtoupper($this->agent->email)]);
    DB::table('users')->where('id', $newcomer->id)->delete();
    $target = freshWorkspace($this->admin);

    (new ImportWorkspace)->handle($this->admin, ['file' => $upload]);

    expect(User::query()->whereKey($oldId)->exists())->toBeFalse()
        ->and(User::query()->whereRaw('lower(email) = ?', [$this->agent->email])->count())->toBe(1)
        ->and(DB::table('workspace_user')->where('workspace_id', $target->id)->where('user_id', $localId)->value('role'))->toBe('agent')
        ->and(DB::table('desk_user')->where('user_id', $localId)->exists())->toBeTrue()
        ->and(DB::table('ticket_user')->value('user_id'))->toBe($localId)
        ->and(SavedView::sole()->user_id)->toBe($localId)
        ->and(SavedView::sole()->filters)->toBe(['assignee_id' => $localId])
        ->and(TicketComment::sole()->author_id)->toBe($localId)
        ->and(TicketComment::sole()->body)->toBe("Thanks <@{$localId}>")
        ->and(Attachment::sole()->uploader_id)->toBe($localId)
        ->and(TicketMetric::sole()->first_responder_id)->toBe($localId)
        ->and(TicketReminder::sole()->created_by)->toBe($localId)
        ->and(Macro::sole()->user_id)->toBe($localId)
        ->and(DB::table('ticket_events')->value('actor_id'))->toBe($localId)
        ->and(DB::table('ticket_events')->value('properties'))->toContain($localId)
        ->and(DB::table('automations')->value('created_by'))->toBe($localId)
        ->and(DB::table('automations')->value('definition'))->toContain($localId);

    foreach (array_keys(BuildExport::WORKSPACE_TABLES) as $table) {
        if (! in_array($table, ['workspaces', 'users'])) {
            expect(DB::table($table)->whereRaw("{$table}::text like ?", ["%{$oldId}%"])->exists())->toBeFalse($table);
        }
    }

    $created = User::query()->where('email', 'newcomer@example.com')->sole();
    expect($created->id)->toBe($newcomer->id)
        ->and($created->password)->not->toBeNull()
        ->and(Hash::check('password', $created->password))->toBeFalse()
        ->and(DB::table('workspace_user')->where('workspace_id', $target->id)->where('user_id', $newcomer->id)->exists())->toBeTrue();
});

test('the importing admin keeps their role and is not duplicated as a member', function () {
    $upload = exportAndDeleteSource($this->admin);
    $target = Workspace::factory()->create(['subdomain' => 'fresh'])->makeCurrent();
    $target->users()->attach($this->admin, ['role' => WorkspaceRole::OWNER]);

    (new ImportWorkspace)->handle($this->admin, ['file' => $upload]);

    expect(DB::table('workspace_user')->where('workspace_id', $target->id)->where('user_id', $this->admin->id)->pluck('role')->all())->toBe(['owner'])
        ->and(DB::table('workspace_user')->where('workspace_id', $target->id)->count())->toBe(2);
});

test('mailboxes come in disconnected and webhook tokens are not carried over', function () {
    seedWorkspace();
    $upload = exportAndDeleteSource($this->admin);
    $target = freshWorkspace($this->admin);

    (new ImportWorkspace)->handle($this->admin, ['file' => $upload]);

    $mailbox = DB::table('mailboxes')->where('driver', 'imap')->sole();
    expect($mailbox->settings)->toBeNull()
        ->and($mailbox->push_secret)->toBeNull()
        ->and($mailbox->push_subscription_id)->toBeNull()
        ->and($mailbox->polled_at)->toBeNull()
        ->and($mailbox->last_error)->toContain('Imported without credentials')
        ->and(DB::table('automations')->where('trigger_type', 'webhook')->value('webhook_token_hash'))->toBeNull()
        ->and($target->desks()->where('slug', 'default')->count())->toBe(1);
});

test('only admins import, on the web and the API', function () {
    $upload = exportAndDeleteSource($this->admin);
    $target = freshWorkspace($this->admin);
    $target->users()->attach($this->agent, ['role' => WorkspaceRole::AGENT]);

    $this->actingAs($this->agent)->post('http://fresh.harbor.test/workspace/import', ['file' => $upload])->assertForbidden();
    actAs($this->agent, $target);
    $this->postJson("$this->api/workspace/import", ['file' => $upload])->assertForbidden();
    expect(Ticket::count())->toBe(0);

    expect(fn () => (new ImportWorkspace)->handle($this->agent, ['file' => $upload]))->toThrow(AuthorizationException::class);

    actAs($this->admin, $target);
    $this->postJson("$this->api/workspace/import")->assertUnprocessable()->assertJsonValidationErrors('file');
    $this->postJson("$this->api/workspace/import", ['file' => $upload])->assertAccepted();

    expect(AuditEvent::query()->where('action', AuditAction::WORKSPACE_IMPORTED)->exists())->toBeTrue();

    $this->actingAs($this->admin)->post('http://fresh.harbor.test/workspace/import', [])->assertSessionHasErrors('file');
});

test('an import never touches another workspace', function () {
    seedWorkspace();
    $upload = exportAndDeleteSource($this->admin);

    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $other->execute(function () use ($other) {
        $contact = Contact::factory()->create();
        Ticket::factory()->create(['desk_id' => $other->desks()->sole()->id, 'requester_id' => $contact->id]);
        Tag::factory()->create();
    });
    $otherBefore = tableCounts($other->id);
    $otherDesk = DB::table('desks')->where('workspace_id', $other->id)->value('id');
    $target = freshWorkspace($this->admin);

    (new ImportWorkspace)->handle($this->admin, ['file' => $upload]);

    expect(tableCounts($other->id))->toBe($otherBefore)
        ->and(DB::table('desks')->where('workspace_id', $other->id)->pluck('id')->all())->toBe([$otherDesk])
        ->and(Ticket::count())->toBe(3);

    foreach (BuildExport::WORKSPACE_TABLES as $table => $_) {
        if (! in_array($table, ['workspaces', 'users', 'audit_events'])) {
            expect(DB::table($table)->where('workspace_id', '!=', $target->id)->where('workspace_id', '!=', $other->id)->exists())->toBeFalse($table);
        }
    }
});

/**
 * Rewrite columns of the first row of an export's table file.
 *
 * @param  array<string, string>  $changes
 */
function tamperRow(string $table, array $changes): Closure
{
    return function (ZipArchive $zip) use ($table, $changes): void {
        $rows = array_map(fn ($line) => json_decode($line, true), array_filter(explode("\n", $zip->getFromName("$table.jsonl"))));
        $rows[0] = [...$rows[0], ...$changes];
        $zip->addFromString("$table.jsonl", implode("\n", array_map('json_encode', $rows))."\n");
    };
}

test('a crafted export cannot point rows at another workspace', function (string $table, string $column, string $otherTable) {
    seedWorkspace();
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $foreignId = $other->execute(function () use ($other, $otherTable) {
        $ticket = Ticket::factory()->create(['desk_id' => $other->desks()->sole()->id, 'requester_id' => Contact::factory()->create()->id]);

        return $otherTable === 'tickets' ? $ticket->id : Tag::factory()->create()->id;
    });
    $this->source->makeCurrent();
    $changes = $table === 'audit_events' ? ['subject_type' => 'ticket', 'subject_id' => $foreignId] : [$column => $foreignId];
    $upload = exportUpload($this->admin, tamperRow($table, $changes));
    DB::table('exports')->delete();
    $this->source->delete();
    $target = freshWorkspace($this->admin);
    $before = tableCounts($target->id);
    $users = User::count();

    (new ImportWorkspace)->handle($this->admin, ['file' => $upload]);

    expect(tableCounts($target->id))->toBe([...$before, 'audit_events' => $before['audit_events'] + 2])
        ->and(User::count())->toBe($users)
        ->and(Storage::disk(config('harbor.attachments.disk'))->allFiles("{$target->id}/tickets"))->toBe([])
        ->and(AuditEvent::query()->where('action', AuditAction::WORKSPACE_IMPORT_FAILED)->exists())->toBeTrue();

    Notification::assertSentTo($this->admin, WorkspaceImportFinishedNotification::class, fn ($notification) => str_contains((string) $notification->error, "{$table}.{$column}"));
})->with([
    'foreign key to a ticket' => ['ticket_comments', 'ticket_id', 'tickets'],
    'pivot to a tag' => ['tag_ticket', 'tag_id', 'tags'],
    'morph subject' => ['audit_events', 'subject_id', 'tickets'],
]);

test('an exported user id that belongs to another user collides instead of attaching that user', function () {
    $upload = exportUpload($this->admin, tamperRow('users', ['email' => 'crafted@example.com']));
    $victim = DB::table('users')->orderBy('id')->first();
    DB::table('exports')->delete();
    $this->source->delete();
    $target = freshWorkspace($this->admin);

    (new ImportWorkspace)->handle($this->admin, ['file' => $upload]);

    expect(DB::table('users')->where('id', $victim->id)->value('email'))->toBe($victim->email)
        ->and(DB::table('users')->where('email', 'crafted@example.com')->exists())->toBeFalse()
        ->and(DB::table('workspace_user')->where('workspace_id', $target->id)->count())->toBe(1)
        ->and(AuditEvent::query()->where('action', AuditAction::WORKSPACE_IMPORT_FAILED)->exists())->toBeTrue();
});
