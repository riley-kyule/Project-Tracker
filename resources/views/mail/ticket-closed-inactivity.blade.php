<x-mail::message>
# Ticket closed — no reply received

Ticket **TK-{{ $ticket->ticket_number }}** ({{ $ticket->title }}) was closed automatically because no reply was received in time.

If this issue isn't actually resolved, you can reopen it. If it is, you can confirm it as resolved instead.

<x-mail::button :url="$url">
View ticket
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
