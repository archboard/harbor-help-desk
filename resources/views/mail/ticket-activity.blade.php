<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
</head>
{{-- Same tokens as the markdown theme (resources/views/vendor/mail/html/themes/default.css), inlined by hand. --}}
<body style="margin: 0; padding: 32px 16px; background: #f4f4f5; font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #3f3f46;">
  <div style="max-width: 570px; margin: 0 auto;">
    <p style="margin: 0 0 24px; text-align: center;">
      <a href="{{ $url }}" style="color: #18181b; font-size: 17px; font-weight: 600; text-decoration: none;">
        <img src="{{ asset('apple-touch-icon.png') }}" width="36" height="36" alt="" style="width: 36px; height: 36px; margin-right: 10px; border-radius: 8px; vertical-align: middle;">
        <span style="vertical-align: middle;">{{ $workspaceName }}</span>
      </a>
    </p>

    <div style="padding: 32px; background: #ffffff; border: 1px solid #e4e4e7; border-radius: 12px; font-size: 15px; line-height: 1.6;">
      @if ($ticket)
        <p style="margin: 0 0 4px; font-size: 13px; color: #71717a;">{{ __('Ticket #:number', ['number' => $ticket->number]) }}</p>
        <p style="margin: 0 0 16px; font-family: 'DM Serif Display', Georgia, 'Times New Roman', serif; font-size: 24px; line-height: 1.25; color: #18181b;">{{ $ticket->subject }}</p>
      @endif

      <p style="margin: 0; color: #18181b;">{{ $description }}</p>

      @if (filled($body))
        <div style="margin-top: 16px; padding: 12px 16px; background: #fafafa; border-left: 3px solid #2563eb; border-radius: 4px; color: #3f3f46; white-space: pre-wrap;">{{ $body }}</div>
      @endif

      <p style="margin: 28px 0 0;">
        <a href="{{ $url }}" style="display: inline-block; padding: 10px 20px; background: #2563eb; border-radius: 8px; color: #ffffff; font-weight: 600; text-decoration: none;">{{ __('Open ticket') }}</a>
      </p>
    </div>

    <p style="margin: 24px 0 0; text-align: center; font-size: 12px; color: #71717a;">{!! __('Sent by :app', ['app' => '<a href="'.e(config('app.url')).'" style="color: #71717a; text-decoration: underline;">'.e(config('app.name')).'</a>']) !!}</p>
  </div>
</body>
</html>
