{!! $marker !!}

{!! $comment->body !!}
@if (filled($mailbox->signature))

{!! $mailbox->signature !!}
@endif

{!! $workspaceName !!} · {!! __('Ticket #:number', ['number' => $ticket->number]) !!}
