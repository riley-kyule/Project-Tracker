<x-mail::message>
# Ticket assigned

{{ $assigner->name }} assigned ticket **TK-{{ $ticket->ticket_number }}** to {{ $ticket->assignee?->name ?? 'a technician' }}: **{{ $ticket->title }}**.

<x-mail::button :url="$url">
View ticket
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
