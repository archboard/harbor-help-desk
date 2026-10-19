<?php

use App\Mail\Inbound\InboundEmail;

function fixtureEmail(string $name): InboundEmail
{
    return InboundEmail::fromMime((string) file_get_contents(__DIR__."/../Fixtures/emails/{$name}.eml"));
}

test('a gmail reply is parsed with threading headers and its quote stripped', function () {
    $email = fixtureEmail('gmail-reply');

    expect($email)
        ->messageId->toBe('CAF=reply123@mail.gmail.com')
        ->inReplyTo->toBe('cmt_abc@harbor.test')
        ->references->toBe(['CAF=first@mail.gmail.com', 'cmt_abc@harbor.test'])
        ->fromEmail->toBe('pat.lee@gmail.com')
        ->fromName->toBe('Pat Lee')
        ->recipients->toBe(['support@acme.com', 'billing@acme.com'])
        ->subject->toBe('Re: Printer jam [#1042]');
    expect($email->replyText())->toBe("Thanks, that fixed it!\n\nPat");
    expect($email->isAutoReply())->toBeFalse();
    expect($email->isBounce())->toBeFalse();
});

test('an outlook reply has its From/Sent block stripped', function () {
    $email = fixtureEmail('outlook-reply');

    expect($email->fromName)->toBe('Lee, Pat');
    expect($email->replyText())->toBe('Still jammed, see below.');
});

test('an html-only email is converted to plain text', function () {
    expect(fixtureEmail('html-only')->text)->toBe("The link on the \"Pricing\" page is broken.\nFish & chips\nSam");
});

test('attachments are extracted and a missing message id falls back to a hash', function () {
    $raw = (string) file_get_contents(__DIR__.'/../Fixtures/emails/with-attachment.eml');
    $email = InboundEmail::fromMime($raw);

    expect($email->messageId)->toBe(hash('sha256', $raw));
    expect($email->text)->toBe('Invoice attached.');
    expect($email->attachments)->toBe([
        ['filename' => 'invoice.pdf', 'mime' => 'application/pdf', 'contents' => '%PDF-1.4 fake'],
    ]);
});

test('auto-replies are detected by their headers', function (array $headers, bool $isAutoReply) {
    $email = new InboundEmail('id', null, [], 'pat@example.com', null, [], 'Hi', 'Hi', [], $headers);

    expect($email->isAutoReply())->toBe($isAutoReply);
})->with([
    'plain mail' => [[], false],
    'auto-submitted: no' => [['auto-submitted' => 'no'], false],
    'auto-submitted' => [['auto-submitted' => 'auto-generated'], true],
    'x-autoreply' => [['x-autoreply' => 'yes'], true],
    'precedence bulk' => [['precedence' => 'Bulk'], true],
    'spam' => [['x-spam-status' => 'Yes, score=9.1'], true],
]);

test('an out-of-office reply is an auto-reply', function () {
    expect(fixtureEmail('out-of-office')->isAutoReply())->toBeTrue();
});

test('a delivery status notification is a bounce that references the original message', function () {
    $email = fixtureEmail('bounce');

    expect($email->isBounce())->toBeTrue();
    expect($email->references)->toBe(['cmt_abc@harbor.test']);
});

test('only the receiving server\'s own authentication results vouch for the sender', function () {
    $mime = fn (string $results) => "Authentication-Results: mx.google.com; {$results}\r\n"
        ."Authentication-Results: forged.example; dmarc=pass header.from=acme.com\r\n"
        ."From: Alex <alex@acme.com>\r\nTo: support@harbor.test\r\nSubject: Re: [#1]\r\nMessage-ID: <a@acme.com>\r\n\r\nHi";

    expect(InboundEmail::fromMime($mime('dmarc=fail header.from=acme.com'))->hasAuthenticatedSender())->toBeFalse()
        ->and(InboundEmail::fromMime($mime('dkim=pass header.d=acme.com.evil.com'))->hasAuthenticatedSender())->toBeFalse()
        ->and(InboundEmail::fromMime($mime('dkim=pass header.d=acme.com; spf=pass'))->hasAuthenticatedSender())->toBeTrue()
        ->and(InboundEmail::fromMime($mime('dmarc=pass header.from=acme.com'))->hasAuthenticatedSender())->toBeTrue();
});
