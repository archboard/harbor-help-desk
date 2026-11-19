<x-mail::customer :$marker :$ticket :$workspaceName :$portalUrl :signature="$mailbox->signature">
<x-mail::plain-text :text="__('We received your request “:subject” and will get back to you soon.', ['subject' => $ticket->subject])" />

{{ __('Reply to this email to add more details.') }}
</x-mail::customer>
