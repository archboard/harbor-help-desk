<?php

use App\Actions\Workspaces\CreateWorkspace;
use App\Actions\Workspaces\DeleteWorkspace;
use App\Actions\Workspaces\UpdateProvisionedWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\Desk;
use App\Models\Mailbox;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('creating a workspace gives it a default desk', function () {
    $workspace = app(CreateWorkspace::class)->handle(['name' => 'Acme', 'subdomain' => 'acme']);

    expect($workspace)->name->toBe('Acme')->subdomain->toBe('acme')
        ->and($workspace->execute(fn () => Desk::sole()->slug))->toBe('default');
});

test('subdomains must be well-formed, unreserved and unused', function (string $subdomain) {
    Workspace::factory()->create(['subdomain' => 'taken']);

    expect(fn () => app(CreateWorkspace::class)->handle(['name' => 'Acme', 'subdomain' => $subdomain]))
        ->toThrow(ValidationException::class);
})->with(['ab', 'Acme', '-acme', 'acme-', 'ac_me', 'www', 'taken']);

test('the subdomain can change until the workspace has members', function () {
    $workspace = Workspace::factory()->create(['subdomain' => 'acme']);
    $update = app(UpdateProvisionedWorkspace::class);

    $update->handle($workspace, ['subdomain' => 'acme-co']);
    expect($workspace->fresh()->subdomain)->toBe('acme-co');

    $workspace->users()->attach(User::factory()->create(), ['role' => WorkspaceRole::OWNER]);

    expect(fn () => $update->handle($workspace, ['subdomain' => 'acme-inc']))->toThrow(ValidationException::class)
        ->and($workspace->fresh()->subdomain)->toBe('acme-co');

    $update->handle($workspace, ['name' => 'Acme Inc', 'subdomain' => 'acme-co']);
    expect($workspace->fresh()->name)->toBe('Acme Inc');
});

test('updating a subdomain to another workspace\'s is rejected', function () {
    Workspace::factory()->create(['subdomain' => 'taken']);
    $workspace = Workspace::factory()->create(['subdomain' => 'acme']);

    expect(fn () => app(UpdateProvisionedWorkspace::class)->handle($workspace, ['subdomain' => 'taken']))
        ->toThrow(ValidationException::class);
});

test('changing the subdomain renames the hosted mailbox', function () {
    config(['harbor.multitenant' => true, 'harbor.mail.inbound_domain' => 'in.harbor.test']);
    $workspace = Workspace::factory()->create(['subdomain' => 'acme']);
    $other = Workspace::factory()->create(['subdomain' => 'globex']);

    app(UpdateProvisionedWorkspace::class)->handle($workspace, ['subdomain' => 'acme-co']);

    expect($workspace->execute(fn () => Mailbox::sole()))
        ->address->toBe('acme-co@in.harbor.test')
        ->inbound_address->toBe('acme-co@in.harbor.test')
        ->and($other->execute(fn () => Mailbox::sole()->address))->toBe('globex@in.harbor.test');
});

test('deleting a workspace removes its rows and files but not another workspace\'s', function () {
    Storage::fake('local');
    $workspace = Workspace::factory()->create();
    $other = Workspace::factory()->create();
    Storage::disk('local')->put("{$workspace->id}/tickets/file.txt", 'a');
    Storage::disk('local')->put("{$other->id}/tickets/file.txt", 'b');

    app(DeleteWorkspace::class)->handle($workspace);

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse()
        ->and(Desk::withoutGlobalScope('workspace')->where('workspace_id', $workspace->id)->exists())->toBeFalse()
        ->and(Desk::withoutGlobalScope('workspace')->where('workspace_id', $other->id)->exists())->toBeTrue();
    Storage::disk('local')->assertMissing("{$workspace->id}/tickets/file.txt");
    Storage::disk('local')->assertExists("{$other->id}/tickets/file.txt");
});
