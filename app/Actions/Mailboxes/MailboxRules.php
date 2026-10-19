<?php

namespace App\Actions\Mailboxes;

use App\Enums\MailboxDriver;
use App\Models\Mailbox;
use App\Models\Workspace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validation rules for creating a mailbox (`$mailbox` null) or updating one. Hosted and OAuth
 * mailboxes can only change their name, signature, default desk and routing.
 */
class MailboxRules
{
    /**
     * @return array<string, array<int, ValidationRule|Closure|string|object>>
     */
    public static function for(?MailboxDriver $driver, ?Mailbox $mailbox = null): array
    {
        $required = $mailbox === null ? 'required' : 'sometimes';
        $ownServer = $driver === MailboxDriver::IMAP;
        $activeDesk = Rule::exists('desks', 'id')->where('workspace_id', Workspace::current()?->getKey())->whereNull('archived_at');

        $rules = [
            'name' => [$required, 'string', 'max:255'],
            'signature' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'default_desk_id' => [$required, 'string', $activeDesk],
            'routing' => ['sometimes', 'array', 'list'],
            'routing.*' => ['array:address,desk_id'],
            'routing.*.address' => ['required', 'string', 'email', 'max:255'],
            'routing.*.desk_id' => ['required', 'string', $activeDesk],
            'address' => [$mailbox === null || $ownServer ? $required : 'missing', 'string', 'email', 'max:255'],
            'settings' => [$mailbox === null || $ownServer ? $required : 'missing', 'array'],
        ];

        if ($mailbox === null) {
            $rules['driver'] = ['required', Rule::enum(MailboxDriver::class)->except(MailboxDriver::HOSTED)];
        }

        if ($ownServer) {
            foreach (['imap', 'smtp'] as $protocol) {
                $rules["settings.{$protocol}"] = [$required, 'array'];
                $rules["settings.{$protocol}.host"] = [$required, 'string', 'max:255', self::publicHost(...)];
                $rules["settings.{$protocol}.port"] = [$required, 'integer', 'between:1,65535'];
                $rules["settings.{$protocol}.encryption"] = [$required, 'in:ssl,tls,starttls'];
                $rules["settings.{$protocol}.username"] = [$required, 'string', 'max:255'];
                // Passwords are never sent back to clients, so an update may leave them out.
                $rules["settings.{$protocol}.password"] = [$required, 'string', 'max:1000'];
            }

            $rules['settings.imap.folder'] = ['sometimes', 'string', 'max:255'];
        }

        if ($mailbox === null && $driver?->usesOAuth()) {
            $rules['settings.access_token'] = ['required', 'string'];
            $rules['settings.refresh_token'] = ['required', 'string'];
            $rules['settings.expires_at'] = ['required', 'integer'];
        }

        return $rules;
    }

    /**
     * In the cloud, the host must resolve only to public IPs, so a tenant can't point Harbor
     * at its own network.
     */
    protected static function publicHost(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('harbor.multitenant') || ! is_string($value)) {
            return;
        }

        // ponytail: checked on save, so DNS rebinding can still swap in a private IP later, and
        // IPv6-only hosts are refused. Pin the resolved IP on connect if either matters.
        $ips = gethostbynamel($value) ?: [];
        $public = fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        if ($ips === [] || ! collect($ips)->every($public)) {
            $fail(__('The :attribute must be a public mail server.'));
        }
    }
}
