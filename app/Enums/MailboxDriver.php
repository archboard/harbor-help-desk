<?php

namespace App\Enums;

use App\Enums\Concerns\HasLabels;
use LogicException;

enum MailboxDriver: string
{
    use HasLabels;

    case HOSTED = 'hosted';
    case IMAP = 'imap';
    case GOOGLE = 'google';
    case MICROSOFT = 'microsoft';

    public function label(): string
    {
        return match ($this) {
            self::HOSTED => __('Hosted'),
            self::IMAP => __('IMAP/SMTP'),
            self::GOOGLE => __('Google'),
            self::MICROSOFT => __('Microsoft 365'),
        };
    }

    public function usesOAuth(): bool
    {
        return $this === self::GOOGLE || $this === self::MICROSOFT;
    }

    /**
     * The provider's OAuth app and endpoints, and the IMAP and SMTP servers its mailboxes
     * connect to with XOAUTH2.
     *
     * @return array{client_id: ?string, client_secret: ?string, authorize_url: string, token_url: string, scopes: string, imap: array{host: string, port: int, encryption: string}, smtp: array{host: string, port: int, encryption: string}}
     */
    public function oauth(): array
    {
        return match ($this) {
            self::GOOGLE => [
                'client_id' => config('services.google_mail.client_id'),
                'client_secret' => config('services.google_mail.client_secret'),
                'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_url' => 'https://oauth2.googleapis.com/token',
                // Restricted scope: cloud production needs Google's verification.
                'scopes' => 'openid email https://mail.google.com/',
                'imap' => ['host' => 'imap.gmail.com', 'port' => 993, 'encryption' => 'ssl'],
                'smtp' => ['host' => 'smtp.gmail.com', 'port' => 465, 'encryption' => 'ssl'],
            ],
            self::MICROSOFT => [
                'client_id' => config('services.microsoft_mail.client_id'),
                'client_secret' => config('services.microsoft_mail.client_secret'),
                'authorize_url' => 'https://login.microsoftonline.com/'.config('services.microsoft_mail.tenant').'/oauth2/v2.0/authorize',
                'token_url' => 'https://login.microsoftonline.com/'.config('services.microsoft_mail.tenant').'/oauth2/v2.0/token',
                // SMTP AUTH must be enabled on the Microsoft 365 tenant for sending. Mail.Read is
                // for Microsoft Graph push subscriptions.
                'scopes' => 'openid email offline_access https://outlook.office.com/IMAP.AccessAsUser.All https://outlook.office.com/SMTP.Send https://graph.microsoft.com/Mail.Read',
                'imap' => ['host' => 'outlook.office365.com', 'port' => 993, 'encryption' => 'ssl'],
                'smtp' => ['host' => 'smtp.office365.com', 'port' => 587, 'encryption' => 'starttls'],
            ],
            default => throw new LogicException("The {$this->value} driver doesn't use OAuth."),
        };
    }
}
