<x-mail::customer :$marker :$ticket :$workspaceName :$portalUrl :signature="$mailbox->signature">
<x-mail::plain-text :text="$comment->body" />
</x-mail::customer>
