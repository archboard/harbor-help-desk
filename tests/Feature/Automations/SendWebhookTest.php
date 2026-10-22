<?php

use App\Automations\AutomationException;
use App\Automations\NotifyCreatorOfFailure;
use App\Automations\SafeUrl;
use App\Enums\AutomationRunStatus;
use App\Enums\TicketEventType;
use App\Enums\WorkspaceRole;
use App\Jobs\SendAutomationWebhook;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Desk;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AutomationNotice;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);

    $this->workspace = Workspace::factory()->create(['subdomain' => 'acme'])->makeCurrent();
    $this->desk = Desk::sole();
    $this->admin = User::factory()->create();
    $this->workspace->users()->attach($this->admin, ['role' => WorkspaceRole::ADMIN]);
    $this->base = 'http://acme.harbor.test/api/v1/automations';

    $this->dns = ['hooks.example.com' => ['93.184.216.34']];
    app()->instance(SafeUrl::class, new SafeUrl(fn (string $host): array => $this->dns[$host] ?? []));

    Http::preventStrayRequests();
});

/**
 * @param  array<string, mixed>  $config
 * @return array<string, mixed>
 */
function webhookInput(array $config): array
{
    return ['name' => 'Hook', 'enabled' => true, 'definition' => [
        'trigger' => ['type' => 'ticket.created', 'config' => []],
        'steps' => [['id' => 's1', 'type' => 'action', 'action' => 'send_webhook', 'config' => $config]],
    ]];
}

/**
 * Replaces the Http fake, since the first stub that matches wins.
 *
 * @param  array<string, mixed>  $stubs
 */
function fakeHttp(array $stubs): void
{
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake($stubs);
    Cache::flush();
}

/**
 * Creates the automation through the API and fires it for a new ticket.
 *
 * @param  array<string, mixed>  $config
 */
function fireWebhook(array $config, array $ticket = []): AutomationRun
{
    actAs(test()->admin, test()->workspace);
    fakeHttp(['hooks.example.com/*' => Http::response('ok', 200)]);
    test()->postJson(test()->base, [...webhookInput($config), 'created_by' => null])->assertCreated();
    Automation::query()->update(['created_by' => test()->admin->id]);

    Ticket::factory()->create($ticket)->recordEvent(TicketEventType::CREATED, null);

    return AutomationRun::query()->latest('id')->firstOrFail();
}

/**
 * The job as the worker runs it on its `$attempt`th try.
 */
function webhookJob(AutomationRun $run, int $attempt = 1, string $url = 'https://hooks.example.com/in'): SendAutomationWebhook
{
    $job = new SendAutomationWebhook($run, 's1', 'POST', $url, [], '{}');
    $queued = Mockery::mock(Job::class);
    $queued->shouldReceive('attempts')->andReturn($attempt);
    $job->setJob($queued);

    return $job;
}

function webhookEntries(AutomationRun $run): array
{
    return array_values(array_filter($run->fresh()->log, fn (array $entry): bool => $entry['type'] === 'webhook'));
}

test('a templated JSON body is escaped, headers are sent, and status and duration land in the run log', function () {
    $run = fireWebhook([
        'method' => 'POST',
        'url' => 'https://hooks.example.com/in?s={{ ticket.subject }}',
        'headers' => [['name' => 'X-Token', 'value' => 'abc'], ['name' => 'X-Subject', 'value' => '{{ ticket.subject }}']],
        'body' => '{"subject": "{{ ticket.subject }}", "number": {{ ticket.number }}}',
    ], ['subject' => "He said \"hi\"\nthen left\\"]);

    expect($run->status)->toBe(AutomationRunStatus::COMPLETED)
        ->and($run->log[0]['result'])->toBe('Queued POST to hooks.example.com');

    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR);

        return $request->method() === 'POST'
            && $request->url() === 'https://hooks.example.com/in?s='.rawurlencode("He said \"hi\"\nthen left\\")
            && $body['subject'] === "He said \"hi\"\nthen left\\"
            && is_int($body['number'])
            && $request->header('X-Token') === ['abc']
            && $request->header('X-Subject') === ['He said "hi"then left\\']
            && $request->header('Content-Type')[0] === 'application/json';
    });

    $entry = webhookEntries($run)[0];
    expect($entry)->toMatchArray(['step_id' => 's1', 'status' => 200, 'attempt' => 1])
        ->and($entry['duration_ms'])->toBeInt()
        ->and($entry)->not->toHaveKey('error')
        ->and(json_encode($entry))->not->toContain('abc');
});

test('a 500 is retried and then logs the success', function () {
    $run = fireWebhook(['method' => 'POST', 'url' => 'https://hooks.example.com/in']);
    AutomationRun::query()->whereKey($run->id)->update(['log' => []]);

    fakeHttp(['hooks.example.com/*' => Http::sequence()->push('', 500)->push('ok', 200)]);

    expect(fn () => webhookJob($run, 1)->handle(app(SafeUrl::class), app(NotifyCreatorOfFailure::class)))
        ->toThrow(AutomationException::class);
    expect(webhookEntries($run))->toBe([]);

    webhookJob($run, 2)->handle(app(SafeUrl::class), app(NotifyCreatorOfFailure::class));

    expect(webhookEntries($run))->toHaveCount(1)->and(webhookEntries($run)[0])->toMatchArray(['status' => 200, 'attempt' => 2]);
});

test('three 500s log the final failure and tell the creator', function () {
    $run = fireWebhook(['method' => 'POST', 'url' => 'https://hooks.example.com/in']);
    AutomationRun::query()->whereKey($run->id)->update(['log' => []]);
    fakeHttp(['hooks.example.com/*' => Http::response('', 500)]);
    Notification::fake();

    webhookJob($run, 3)->handle(app(SafeUrl::class), app(NotifyCreatorOfFailure::class));

    expect(webhookEntries($run)[0])->toMatchArray(['status' => 500, 'attempt' => 3])->toHaveKey('error')
        ->and($run->fresh()->status)->toBe(AutomationRunStatus::COMPLETED);
    Notification::assertSentTo($this->admin, AutomationNotice::class);
});

test('a 404 is final and not retried, and a redirect is not followed', function () {
    $run = fireWebhook(['method' => 'POST', 'url' => 'https://hooks.example.com/in']);
    AutomationRun::query()->whereKey($run->id)->update(['log' => []]);
    Notification::fake();

    fakeHttp(['hooks.example.com/*' => Http::sequence()->push('', 404)->push('', 302, ['Location' => 'http://127.0.0.1/admin'])]);
    webhookJob($run, 1)->handle(app(SafeUrl::class), app(NotifyCreatorOfFailure::class));
    expect(webhookEntries($run)[0])->toMatchArray(['status' => 404, 'attempt' => 1]);

    webhookJob($run, 1)->handle(app(SafeUrl::class), app(NotifyCreatorOfFailure::class));

    expect(webhookEntries($run)[1])->toMatchArray(['status' => 302]);
    Http::assertSentCount(2);
});

test('the url is vetted again on every attempt, so DNS turning private blocks the request', function () {
    $run = fireWebhook(['method' => 'POST', 'url' => 'https://hooks.example.com/in']);
    AutomationRun::query()->whereKey($run->id)->update(['log' => []]);
    fakeHttp(['hooks.example.com/*' => Http::sequence()->push('', 500)->push('ok', 200)]);
    Notification::fake();

    expect(fn () => webhookJob($run, 1)->handle(app(SafeUrl::class), app(NotifyCreatorOfFailure::class)))->toThrow(AutomationException::class);

    $this->dns['hooks.example.com'] = ['10.0.0.7'];
    webhookJob($run, 2)->handle(app(SafeUrl::class), app(NotifyCreatorOfFailure::class));

    Http::assertSentCount(1);
    expect(webhookEntries($run)[0])->toMatchArray(['status' => null, 'attempt' => 2])
        ->and(webhookEntries($run)[0]['error'])->toContain('private');
});

test('saving validates the method, the url, headers and the body template', function () {
    actAs($this->admin, $this->workspace);

    $invalid = fn (array $config, string $key) => $this->postJson($this->base, webhookInput($config))
        ->assertUnprocessable()->assertJsonValidationErrors(["definition.steps.s1.config.$key"]);

    $ok = ['method' => 'POST', 'url' => 'https://hooks.example.com/in'];

    $invalid([...$ok, 'method' => 'TRACE'], 'method');
    $invalid([...$ok, 'url' => 'ftp://hooks.example.com/in'], 'url');
    $invalid([...$ok, 'url' => 'http://user:pw@hooks.example.com/'], 'url');
    $invalid([...$ok, 'url' => 'http://2130706433/'], 'url');
    $invalid([...$ok, 'headers' => [['name' => 'Host', 'value' => 'evil']]], 'headers.0.name');
    $invalid([...$ok, 'headers' => [['name' => 'content-length', 'value' => '1']]], 'headers.0.name');
    $invalid([...$ok, 'headers' => [['name' => 'Bad Name', 'value' => '1']]], 'headers.0.name');
    $invalid([...$ok, 'body' => '{"a": '], 'body');

    $this->postJson($this->base, webhookInput([...$ok, 'url' => 'https://{{ ticket.number }}.example.com/', 'body' => '{"a": "{{ ticket.subject }}"}']))->assertCreated();
});

test('a body that is not valid json once rendered fails the run safely', function () {
    $run = fireWebhook(['method' => 'POST', 'url' => 'https://hooks.example.com/in', 'body' => '{"subject": {{ ticket.subject }}}']);

    expect($run->status)->toBe(AutomationRunStatus::FAILED)->and($run->error)->toContain('not valid JSON');
    Http::assertNothingSent();
});

test('a job runs in its own workspace, whatever another workspace stores', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $other->update(['settings' => ['automations_allow_private_urls' => true]]);
    DB::table('workspaces')->where('id', $this->workspace->id)->update(['settings' => '{"automations_allow_private_urls":true}']);

    $this->dns['hooks.example.com'] = ['10.0.0.7'];
    Notification::fake();

    $run = fireWebhook(['method' => 'POST', 'url' => 'https://hooks.example.com/in']);

    // Stored true is ignored in multitenant mode, so the private address stays blocked.
    Http::assertNothingSent();
    expect(webhookEntries($run)[0])->toHaveKey('error');

    $runB = $other->execute(function () {
        $automation = Automation::factory()->create();

        return AutomationRun::query()->create([
            'workspace_id' => Workspace::current()->id, 'automation_id' => $automation->id, 'depth' => 0,
            'status' => AutomationRunStatus::COMPLETED, 'definition' => $automation->definition, 'log' => [], 'state' => [],
        ]);
    });

    $this->workspace->execute(fn () => $this->postJson($this->base, [])); // keeps A current

    $other->execute(fn () => webhookJob($runB)->handle(app(SafeUrl::class), app(NotifyCreatorOfFailure::class)));

    expect(webhookEntries($run))->toHaveCount(1)
        ->and(DB::table('automation_runs')->where('id', $runB->id)->value('log'))->toContain('"type": "webhook"');
});

test('workspace A can not read or change workspace B settings through the api', function () {
    $other = Workspace::factory()->create(['subdomain' => 'other']);
    $other->update(['settings' => ['mail_new_tickets_per_sender_per_hour' => 7]]);

    actAs($this->admin, $this->workspace);
    $this->patchJson('http://other.harbor.test/api/v1/workspace/settings', ['mail_new_tickets_per_sender_per_hour' => 9])->assertForbidden();
    $this->patchJson('http://acme.harbor.test/api/v1/workspace/settings', ['mail_new_tickets_per_sender_per_hour' => 5])
        ->assertOk()->assertJsonPath('settings.mail_new_tickets_per_sender_per_hour', 5);

    expect($other->fresh()->settings->mailNewTicketsPerSenderPerHour)->toBe(7);
});
