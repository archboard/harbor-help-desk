<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="margin: 0; padding: 24px; background: #f9fafb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #111827;">
  <div style="max-width: 600px; padding: 24px; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 15px; line-height: 1.6;">
    <p style="margin: 0;">{{ $description }}</p>

    @if (filled($body))
      <div style="margin-top: 16px; padding-left: 12px; border-left: 3px solid #e5e7eb; color: #374151; white-space: pre-wrap;">{{ $body }}</div>
    @endif

    <p style="margin: 24px 0 0;">
      <a href="{{ $url }}" style="display: inline-block; padding: 8px 16px; background: #111827; border-radius: 6px; color: #ffffff; text-decoration: none;">{{ __('Open ticket') }}</a>
    </p>
  </div>

  <p style="margin: 16px 0 0; font-size: 12px; color: #9ca3af;">
    {{ $workspaceName }}@if ($ticket) · {{ __('Ticket #:number', ['number' => $ticket->number]) }}@endif
  </p>
</body>
</html>
