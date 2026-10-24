<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin: 0; padding: 24px; background: #f9fafb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111827;">
  <p style="margin: 0 0 16px; font-size: 12px; color: #9ca3af;">{{ $marker }}</p>

  <div style="max-width: 600px; padding: 24px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 15px; line-height: 1.6;">
    <div style="white-space: pre-wrap;">{{ $comment->body }}</div>

    @if (filled($mailbox->signature))
      <div style="margin-top: 24px; color: #4b5563; white-space: pre-wrap;">{{ $mailbox->signature }}</div>
    @endif
  </div>

  <p style="margin: 16px 0 0; font-size: 12px; color: #9ca3af;">
    {{ $workspaceName }} · {{ __('Ticket #:number', ['number' => $ticket->number]) }}
    @if ($portalUrl)
      <br><a href="{{ $portalUrl }}" style="color: #6b7280;">{{ __('View this ticket online') }}</a>
    @endif
  </p>
</body>
</html>
