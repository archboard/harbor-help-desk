<?php

namespace App\Mail\Inbound;

use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\IdHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Message\IMessagePart;

/**
 * An email received by a mailbox, parsed from raw MIME. Message IDs are stored without angle
 * brackets, and addresses are lowercased.
 */
final readonly class InboundEmail
{
    /**
     * The line placed above quoted history in outgoing replies.
     */
    public const string REPLY_MARKER = '##- Reply above this line -##';

    /**
     * @param  array<int, string>  $references
     * @param  array<int, string>  $recipients  To, Cc, Delivered-To and X-Original-To
     * @param  array<int, array{filename: string, mime: string, contents: string}>  $attachments
     * @param  array<string, string>  $headers  the first value of each header, keyed by lowercase name
     */
    public function __construct(
        public string $messageId,
        public ?string $inReplyTo,
        public array $references,
        public string $fromEmail,
        public ?string $fromName,
        public array $recipients,
        public string $subject,
        public string $text,
        public array $attachments,
        public array $headers,
    ) {}

    public static function fromMime(string $raw): self
    {
        $message = (new MailMimeParser)->parse($raw, false);

        $from = $message->getHeader('From');
        $inReplyTo = $message->getHeader('In-Reply-To');
        $references = $message->getHeader('References');

        $headers = [];
        foreach ($message->getAllHeaders() as $header) {
            $headers[strtolower($header->getName())] ??= trim($header->getRawValue());
        }

        $html = $message->getHtmlContent();
        $text = $message->getTextContent() ?? ($html === null ? '' : self::htmlToText($html));

        return new self(
            messageId: $message->getMessageId() ?: hash('sha256', $raw),
            inReplyTo: $inReplyTo instanceof IdHeader ? $inReplyTo->getId() : null,
            references: [
                ...($references instanceof IdHeader ? $references->getIds() : []),
                ...self::bouncedMessageIds($message),
            ],
            fromEmail: strtolower($from instanceof AddressHeader ? (string) $from->getEmail() : ''),
            fromName: $from instanceof AddressHeader ? ($from->getPersonName() ?: null) : null,
            recipients: self::recipients($message),
            subject: trim((string) $message->getSubject()),
            text: trim(str_replace(["\r\n", "\r"], "\n", $text)),
            attachments: array_map(fn (IMessagePart $part): array => [
                'filename' => $part->getFilename() ?: 'attachment',
                'mime' => $part->getContentType('application/octet-stream'),
                'contents' => (string) $part->getBinaryContentStream()?->getContents(),
            ], $message->getAllAttachmentParts()),
            headers: $headers,
        );
    }

    /**
     * The new text of a reply, without the quoted history below it.
     *
     * ponytail: line-based heuristics for the common clients (Gmail, Apple Mail, Outlook). Clients
     * that quote in other languages or formats keep their history; add patterns as they show up.
     */
    public function replyText(): string
    {
        $patterns = [
            '/^.*'.preg_quote(self::REPLY_MARKER, '/').'/m',
            '/^On\b[^\n]{0,300}(\n[^\n]{0,300})?\bwrote:[ \t]*$/m',
            '/^-{2,}\s*Original Message\s*-{2,}/mi',
            '/^_{10,}[ \t]*\n(?=From:)/m',
            '/^From:[ \t].*\n(?:.*\n){0,3}?(?:Sent|Date):[ \t]/m',
        ];

        $cut = strlen($this->text);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $this->text, $match, PREG_OFFSET_CAPTURE) === 1) {
                $cut = min($cut, $match[0][1]);
            }
        }

        $reply = trim((string) preg_replace('/(?:\n[ \t]*>.*|\n[ \t]*)+$/', '', "\n".substr($this->text, 0, $cut)));

        return $reply === '' ? $this->text : $reply;
    }

    /**
     * Out-of-office replies, autoresponders, mailing-list and spam-flagged mail.
     */
    public function isAutoReply(): bool
    {
        $autoSubmitted = strtolower($this->headers['auto-submitted'] ?? 'no');
        $precedence = strtolower($this->headers['precedence'] ?? '');

        return $autoSubmitted !== 'no'
            || isset($this->headers['x-autoreply'])
            || isset($this->headers['x-autorespond'])
            || in_array($precedence, ['bulk', 'junk', 'list', 'auto_reply'], true)
            || str_starts_with(strtolower($this->headers['x-spam-status'] ?? ''), 'yes');
    }

    /**
     * Delivery status notifications, or anything from a mailer daemon.
     */
    public function isBounce(): bool
    {
        $contentType = strtolower($this->headers['content-type'] ?? '');

        return (str_contains($contentType, 'multipart/report') && str_contains($contentType, 'delivery-status'))
            || in_array(strstr($this->fromEmail, '@', true), ['mailer-daemon', 'postmaster'], true);
    }

    /**
     * @return array<int, string>
     */
    protected static function recipients(IMessage $message): array
    {
        $addresses = [];

        foreach (['To', 'Cc', 'Delivered-To', 'X-Original-To'] as $name) {
            foreach ($message->getAllHeadersByName($name) as $header) {
                $emails = $header instanceof AddressHeader
                    ? array_map(fn ($address): string => $address->getEmail(), $header->getAddresses())
                    : [trim((string) $header->getValue(), ' <>')];

                array_push($addresses, ...$emails);
            }
        }

        return array_values(array_unique(array_filter(array_map(strtolower(...), $addresses))));
    }

    /**
     * The Message-ID of the original message quoted in a delivery status notification, which
     * usually has no In-Reply-To of its own.
     *
     * @return array<int, string>
     */
    protected static function bouncedMessageIds(IMessage $message): array
    {
        $original = $message->getPartByMimeType('text/rfc822-headers') ?? $message->getPartByMimeType('message/rfc822');

        if ($original === null || preg_match('/^Message-ID:\s*<?([^>\s]+)>?/mi', (string) $original->getContent(), $match) !== 1) {
            return [];
        }

        return [$match[1]];
    }

    protected static function htmlToText(string $html): string
    {
        $html = (string) preg_replace('#<(head|style|script)\b.*?</\1>#is', '', $html);
        $html = (string) preg_replace('#<br\s*/?>|</(p|div|li|tr|h[1-6]|blockquote|table)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return (string) preg_replace("/\n[ \t]*\n(?:[ \t]*\n)+/", "\n\n", (string) preg_replace('/[ \t]+/', ' ', $text));
    }
}
