<x-mail::message>
# Your weekly summary — {{ now()->format('M j, Y') }}

Hi {{ $recipient->name }}, here's how your week looked.

**{{ $completedCount }}** tasks completed this week.

@if ($completed->isNotEmpty())
<x-mail::table>
| Completed |
| :--- |
@foreach ($completed as $line)
| {{ $line }} |
@endforeach
</x-mail::table>
@endif

## Where things stand

<x-mail::table>
| Overdue | Due today | Blocked | Awaiting approval | In progress | Planned later | Backlog |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| {{ $pendingBreakdown['overdue'] ?? 0 }} | {{ $pendingBreakdown['due_today'] ?? 0 }} | {{ $pendingBreakdown['blocked'] ?? 0 }} | {{ $pendingBreakdown['awaiting_approval'] ?? 0 }} | {{ $pendingBreakdown['in_progress'] ?? 0 }} | {{ $pendingBreakdown['planned_later'] ?? 0 }} | {{ $pendingBreakdown['unscheduled_backlog'] ?? 0 }} |
</x-mail::table>

<x-mail::button :url="route('dashboard')">
View my dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
