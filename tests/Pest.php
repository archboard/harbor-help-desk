<?php

use App\Enums\DeskRole;
use App\Enums\WorkspaceRole;
use App\Mail\Inbound\ImapMailboxClient;
use App\Models\Desk;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Mockery\MockInterface;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * A workspace agent on the desk, named Jane Doe unless the attributes say otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function deskMember(Workspace $workspace, Desk $desk, DeskRole $role = DeskRole::AGENT, array $attributes = []): User
{
    $user = User::factory()->create(['name' => 'Jane Doe', ...$attributes]);
    $workspace->users()->attach($user, ['role' => WorkspaceRole::AGENT]);
    $desk->users()->attach($user, ['role' => $role, 'workspace_id' => $workspace->id]);

    return $user;
}

/**
 * Authenticate API requests as the user with a token scoped to the workspace.
 */
function actAs(User $user, Workspace $workspace): void
{
    Passport::actingAs($user, [$workspace->tokenScope()]);
}

/**
 * The acme workspace with an agent on its default desk, made current.
 *
 * @return array{workspace: Workspace, desk: Desk, agent: User}
 */
function acmeWithAgent(): array
{
    $workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $desk = Desk::sole();

    return ['workspace' => $workspace, 'desk' => $desk, 'agent' => deskMember($workspace, $desk)];
}

/**
 * Bind a mocked IMAP client that reports the given folder status for every mailbox.
 */
function mockImapClient(int $uidValidity, int $uidNext): MockInterface
{
    $client = Mockery::mock(ImapMailboxClient::class);
    $client->shouldReceive('status')->andReturn(['uid_validity' => $uidValidity, 'uid_next' => $uidNext]);
    $client->shouldReceive('disconnect');
    app()->bind(ImapMailboxClient::class, fn () => $client);

    return $client;
}
