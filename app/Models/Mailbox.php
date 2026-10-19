<?php

namespace App\Models;

use App\Enums\MailboxDriver;
use App\Mail\Inbound\InboundEmail;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\MailboxFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

/**
 * An email inbox that belongs to the workspace. Mail it receives goes to the desk picked by
 * its ordered routing rules, or to its default desk. The hosted driver receives through
 * Harbor's inbound domain; the others are the workspace's own IMAP/SMTP or OAuth mailboxes.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $default_desk_id
 * @property array<array-key, mixed> $routing
 * @property MailboxDriver $driver
 * @property string $name
 * @property string $address
 * @property string|null $inbound_address
 * @property array<array-key, mixed>|null $settings
 * @property string|null $signature
 * @property int|null $imap_uid_validity
 * @property int|null $imap_last_uid
 * @property CarbonImmutable|null $polled_at
 * @property string|null $last_error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Desk|null $defaultDesk
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\MailboxFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereDefaultDeskId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereDriver($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereImapLastUid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereImapUidValidity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereInboundAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereLastError($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox wherePolledAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereRouting($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereSettings($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereSignature($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Mailbox whereWorkspaceId($value)
 * @method static Builder<static>|Mailbox needsScheduledPoll()
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class Mailbox extends Model
{
    /** @use HasFactory<MailboxFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'routing' => '[]',
    ];

    public function idPrefix(): string
    {
        return 'mbx_';
    }

    /**
     * @return BelongsTo<Desk, $this>
     */
    public function defaultDesk(): BelongsTo
    {
        return $this->belongsTo(Desk::class, 'default_desk_id')->withTrashed();
    }

    /**
     * The mailbox a ticket's replies go out through: the one its thread came in on, else the
     * one whose default desk is the ticket's desk, else the workspace's oldest.
     */
    public static function forTicket(Ticket $ticket): ?self
    {
        return $ticket->mailbox
            ?? self::query()->where('default_desk_id', $ticket->desk_id)->oldest('id')->first()
            ?? self::query()->oldest('id')->first();
    }

    /**
     * The address replies are sent From: Harbor's hosted address, or the workspace's own.
     */
    public function fromAddress(): Address
    {
        return new Address($this->driver === MailboxDriver::HOSTED ? (string) $this->inbound_address : $this->address, $this->name);
    }

    /**
     * The mailer that sends as this mailbox: the app's default mailer (Postmark in the cloud)
     * for the hosted mailbox, otherwise the workspace's own SMTP server.
     */
    public function mailer(): Mailer
    {
        if ($this->driver === MailboxDriver::HOSTED) {
            return Mail::mailer();
        }

        $name = "mailbox-{$this->id}";
        $smtp = $this->settings['smtp'] ?? [];

        config(["mail.mailers.{$name}" => [
            'transport' => 'smtp',
            'scheme' => ($smtp['encryption'] ?? null) === 'ssl' ? 'smtps' : 'smtp',
            'host' => $smtp['host'] ?? '',
            'port' => (int) ($smtp['port'] ?? 587),
            'username' => $smtp['username'] ?? null,
            'password' => $smtp['password'] ?? null,
            'timeout' => 30,
        ]]);

        // Long-running workers cache mailers by name; drop it so changed settings apply.
        Mail::purge($name);

        return Mail::mailer($name);
    }

    /**
     * Limit to the mailboxes `mailboxes:poll` should queue a poll for this minute. Hosted
     * mailboxes receive by webhook, so only the workspace's own mailboxes are polled. While
     * `mailboxes:watch` runs, only those it hasn't polled for 5 minutes are, as a backstop.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function needsScheduledPoll(Builder $query): void
    {
        $query->where('driver', '!=', MailboxDriver::HOSTED)
            ->when(config('harbor.mail.watch'), fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereNull('polled_at')->orWhere('polled_at', '<', now()->subMinutes(5)),
            ));
    }

    /**
     * The desk new mail goes to: the first routing rule whose address is among the recipients
     * and whose desk is active, then the default desk, then the workspace's oldest active desk.
     */
    public function deskFor(InboundEmail $email): Desk
    {
        $desks = Desk::query()->whereKey([...array_column($this->routing, 'desk_id'), $this->default_desk_id])->get()->keyBy('id');

        foreach ($this->routing as $rule) {
            if (in_array(strtolower($rule['address']), $email->recipients, true) && $desks->has($rule['desk_id'])) {
                return $desks[$rule['desk_id']];
            }
        }

        return $desks[$this->default_desk_id] ?? Desk::query()->oldest()->firstOrFail();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'driver' => MailboxDriver::class,
            'routing' => 'array',
            'settings' => 'encrypted:array',
            'imap_uid_validity' => 'integer',
            'imap_last_uid' => 'integer',
            'polled_at' => 'datetime',
        ];
    }
}
