{!! $description !!}
@if (filled($body))

{!! $body !!}
@endif

{!! __('Open ticket') !!}: {!! $url !!}

{!! $workspaceName !!}@if ($ticket) · {!! __('Ticket #:number', ['number' => $ticket->number]) !!}@endif
