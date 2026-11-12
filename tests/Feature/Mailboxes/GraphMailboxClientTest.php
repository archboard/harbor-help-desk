<?php

use App\Mail\Inbound\GraphMailboxClient;
use App\Models\Mailbox;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->freezeSecond();
    $this->graph = new GraphMailboxClient(Mailbox::factory()->microsoft()->create());
});

test('starting follows every page to a delta link that only reports mail from now on', function () {
    Http::fake([
        'graph.microsoft.com/v1.0/me/mailFolders*' => Http::response(['value' => [['id' => 'old']], '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/page-2']),
        'graph.microsoft.com/v1.0/page-2' => Http::response(['value' => [], '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/delta-1']),
    ]);

    expect($this->graph->start())->toBe('https://graph.microsoft.com/v1.0/delta-1');

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return str_contains($request->url(), "mailFolders('Inbox')/messages/delta")
            && $query['$filter'] === 'receivedDateTime ge '.now()->toIso8601ZuluString()
            && $request->hasHeader('Authorization', 'Bearer access-1');
    });
    Http::assertSentCount(2);
});

test('a page lists added and changed messages but not removed ones', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response([
        'value' => [['id' => 'msg-1'], ['id' => 'msg-2', '@removed' => ['reason' => 'deleted']], ['id' => 'msg-3']],
        '@odata.deltaLink' => 'https://graph.microsoft.com/v1.0/delta-2',
    ])]);

    expect($this->graph->page('https://graph.microsoft.com/v1.0/delta-1'))->toBe([
        'ids' => ['msg-1', 'msg-3'],
        'next_link' => null,
        'delta_link' => 'https://graph.microsoft.com/v1.0/delta-2',
    ]);
});

test('a message is downloaded as raw MIME', function () {
    Http::fake(['graph.microsoft.com/v1.0/me/messages/msg-1/$value' => Http::response("Subject: Hi\r\n\r\nHello")]);

    expect($this->graph->raw('msg-1'))->toBe("Subject: Hi\r\n\r\nHello");
});

test('an expired delta link surfaces as a 410 for the poll to restart', function () {
    Http::fake(['graph.microsoft.com/*' => Http::response(['error' => ['code' => 'syncStateNotFound']], 410)]);

    $this->graph->page('https://graph.microsoft.com/v1.0/delta-1');
})->throws(RequestException::class);
