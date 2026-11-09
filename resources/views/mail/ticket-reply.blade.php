<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
</head>
{{-- Goes to customers from the workspace's own mailbox, so it carries the workspace name, not Harbor's logo. --}}
<body style="margin: 0; padding: 32px 16px; background: #f4f4f5; font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #3f3f46;">
  <div style="max-width: 570px; margin: 0 auto;">
    <p style="margin: 0 0 16px; font-size: 12px; color: #a1a1aa;">{{ $marker }}</p>

    <div style="padding: 32px; background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; font-size: 15px; line-height: 1.6; color: #18181b;">
      <div style="white-space: pre-wrap;">{{ $comment->body }}</div>

      @if (filled($mailbox->signature))
        <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #e4e4e7; color: #52525b; white-space: pre-wrap;">{{ $mailbox->signature }}</div>
      @endif
    </div>

    <p style="margin: 24px 0 0; text-align: center; font-size: 12px; color: #71717a;">
      {{ $workspaceName }} · {{ __('Ticket #:number', ['number' => $ticket->number]) }}
      @if ($portalUrl)
        <br><a href="{{ $portalUrl }}" style="color: #2563eb;">{{ __('View this ticket online') }}</a>
      @endif
    </p>
  </div>
</body>
</html>
