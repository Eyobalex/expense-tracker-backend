<x-mail::message>
# {{ $notification->title }}

{{ $notification->body }}

This notification was generated from your server-authoritative financial data.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
