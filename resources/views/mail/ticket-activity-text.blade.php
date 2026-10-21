{!! $description !!}
@if (filled($body))

{!! $body !!}
@endif

{!! __('Open ticket') !!}: {!! $url !!}

{!! $workspaceName !!} · {!! __('Ticket #:number', ['number' => $ticket->number]) !!}
