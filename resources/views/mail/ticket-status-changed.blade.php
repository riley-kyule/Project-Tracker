<x-mail::message>
# Ticket status update

Ticket **TK-{{ $ticket->ticket_number }}** ({{ $ticket->title }}) is now **{{ $status }}**.

<x-mail::button :url="$url">
View ticket
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
