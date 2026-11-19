@props(['marker', 'ticket', 'workspaceName', 'signature' => null, 'portalUrl' => null])
{{-- Goes to customers from the workspace's own mailbox, so it carries the workspace name, not Harbor's logo. --}}
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="$portalUrl" :logo="false">
{{ $workspaceName }}
</x-mail::header>
</x-slot:header>

<x-mail::plain-text :text="$marker" class="reply-marker" />

{!! $slot !!}

@if (filled($signature))
<x-mail::plain-text :text="$signature" class="signature" />
@endif

<x-slot:footer>
<x-mail::footer>
{{ __('Ticket #:number', ['number' => $ticket->number]) }}
@if ($portalUrl)

[{{ __('View this ticket online') }}]({{ $portalUrl }})
@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
