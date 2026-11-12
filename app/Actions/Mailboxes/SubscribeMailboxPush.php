<?php

namespace App\Actions\Mailboxes;

use App\Enums\MailboxDriver;
use App\Mail\Inbound\GraphMailboxClient;
use App\Models\Mailbox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SubscribeMailboxPush
{
    /**
     * Ask Gmail or Microsoft Graph to notify Harbor when the mailbox gets mail (or renew that
     * ask). The notification only triggers a `PollMailbox`. A failure clears the push columns,
     * so the mailbox goes back to polling or watching.
     */
    public function handle(Mailbox $mailbox): void
    {
        if (! $this->enabled($mailbox)) {
            return;
        }

        try {
            $subscription = match ($mailbox->driver) {
                MailboxDriver::GOOGLE => $this->gmail($mailbox),
                default => $this->graph($mailbox),
            };
            $mailbox->forceFill($subscription)->save();
        } catch (Throwable $exception) {
            $mailbox->forceFill([
                'push_subscription_id' => null,
                'push_expires_at' => null,
                'push_secret' => null,
                'last_error' => "Push notifications failed: {$exception->getMessage()}",
            ])->save();
        }
    }

    /**
     * Push needs the secret, a public https URL and, for Gmail, the Pub/Sub topic.
     */
    public function enabled(Mailbox $mailbox): bool
    {
        return $mailbox->driver->usesOAuth()
            && filled(config('harbor.mail.push_secret'))
            && str_starts_with((string) config('app.url'), 'https://')
            && ($mailbox->driver !== MailboxDriver::GOOGLE || filled(config('services.google_mail.pubsub_topic')));
    }

    /**
     * @return array<string, mixed>
     */
    protected function gmail(Mailbox $mailbox): array
    {
        $watch = Http::withToken(app(RefreshMailboxToken::class)->handle($mailbox))
            ->post('https://gmail.googleapis.com/gmail/v1/users/me/watch', [
                'topicName' => config('services.google_mail.pubsub_topic'),
                'labelIds' => ['INBOX'],
            ])->throw()->json();

        // A Gmail watch has no subscription id; calling watch() again renews it.
        return [
            'push_subscription_id' => null,
            'push_expires_at' => CarbonImmutable::createFromTimestampMs((int) $watch['expiration']),
        ];
    }

    /**
     * Graph subscriptions can't be renewed with the same call, so each renewal makes a new one.
     * ponytail: the old one expires on its own within 3 days; duplicate notifications only
     * queue a unique `PollMailbox`.
     *
     * @return array<string, mixed>
     */
    protected function graph(Mailbox $mailbox): array
    {
        $secret = $mailbox->push_secret ?? Str::random(40);
        $url = rtrim((string) config('app.url'), '/').route('webhooks.microsoft.graph', absolute: false);

        $expires = now()->addDays(2);
        $subscription = Http::withToken(app(RefreshMailboxToken::class)->handle($mailbox))->post(GraphMailboxClient::BASE_URL.'/subscriptions', [
            'changeType' => 'created',
            'resource' => "me/mailFolders('Inbox')/messages",
            'notificationUrl' => $url,
            'lifecycleNotificationUrl' => $url,
            'expirationDateTime' => $expires->toIso8601ZuluString(),
            'clientState' => self::clientState($mailbox, $secret),
        ])->throw()->json();

        return [
            'push_subscription_id' => $subscription['id'],
            'push_expires_at' => $expires,
            'push_secret' => $secret,
        ];
    }

    /**
     * `{mailbox_id}.{hmac}`: the id finds the mailbox, the hmac proves the notification is ours.
     */
    public static function clientState(Mailbox $mailbox, string $secret): string
    {
        return $mailbox->id.'.'.hash_hmac('sha256', $mailbox->id, $secret);
    }
}
