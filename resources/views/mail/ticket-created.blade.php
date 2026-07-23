<x-mail::message>
# Ticket received

Ticket **TK-{{ $ticket->ticket_number }}** has been logged: **{{ $ticket->title }}**.

Priority: {{ ucfirst($ticket->priority) }}

<x-mail::button :url="$url">
View ticket
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
