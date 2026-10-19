<?php

namespace App\Http\Resources;

use App\Enums\MailboxDriver;
use App\Models\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * Never exposes passwords or OAuth tokens: only the servers and usernames of an IMAP/SMTP
 * mailbox's settings.
 *
 * @mixin Mailbox
 */
class MailboxResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'driver' => $this->driver->value,
            'driver_label' => $this->driver->label(),
            'name' => $this->name,
            'address' => $this->address,
            'inbound_address' => $this->inbound_address,
            'default_desk_id' => $this->default_desk_id,
            'routing' => $this->routing,
            'signature' => $this->signature,
            'settings' => $this->when($this->driver === MailboxDriver::IMAP, fn (): array => [
                'imap' => Arr::only($this->settings['imap'] ?? [], ['host', 'port', 'encryption', 'username', 'folder']),
                'smtp' => Arr::only($this->settings['smtp'] ?? [], ['host', 'port', 'encryption', 'username']),
            ]),
            'receives_by' => match (true) {
                $this->driver === MailboxDriver::HOSTED => 'webhook',
                (bool) $this->push_expires_at?->isFuture() => 'push',
                (bool) config('harbor.mail.watch') => 'watch',
                default => 'poll',
            },
            'polled_at' => $this->polled_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
