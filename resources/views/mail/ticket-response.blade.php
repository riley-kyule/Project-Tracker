<x-mail::message>
# New response on TK-{{ $ticket->ticket_number }}

{{ $comment->user->name }} wrote:

> {{ $comment->body }}

<x-mail::button :url="$url">
Reply on the ticket
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
