{!! $marker !!}

{!! $comment->body !!}
@if (filled($mailbox->signature))

{!! $mailbox->signature !!}
@endif

{!! $workspaceName !!} · {!! __('Ticket #:number', ['number' => $ticket->number]) !!}
@if ($portalUrl)

{!! __('View this ticket online') !!}: {!! $portalUrl !!}
@endif
