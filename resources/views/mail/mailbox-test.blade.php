<x-mail::message>
# {{ __('Your mailbox can send mail') }}

{{ __(':name sent this test email from :app. Replies and notifications from :address will go out the same way.', ['name' => $mailbox->name, 'app' => config('app.name'), 'address' => $mailbox->fromAddress()->address]) }}
</x-mail::message>
