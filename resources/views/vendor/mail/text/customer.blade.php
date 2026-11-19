@props(['marker', 'ticket', 'workspaceName', 'signature' => null, 'portalUrl' => null])
{!! $marker !!}

{!! $slot !!}
@if (filled($signature))

{!! $signature !!}
@endif

{{ $workspaceName }} · {{ __('Ticket #:number', ['number' => $ticket->number]) }}
@if ($portalUrl)

{{ __('View this ticket online') }}: {{ $portalUrl }}
@endif
