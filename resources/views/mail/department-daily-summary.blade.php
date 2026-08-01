<x-mail::message>
# {{ $department->name }} — daily summary

**{{ $completedToday }}** tasks completed today, **{{ $pending }}** still pending.

@if (! empty($pendingBreakdown))
<x-mail::table>
| Overdue | Due today | Blocked | Awaiting approval | In progress | Planned later | Backlog |
| ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| {{ $pendingBreakdown['overdue'] ?? 0 }} | {{ $pendingBreakdown['due_today'] ?? 0 }} | {{ $pendingBreakdown['blocked'] ?? 0 }} | {{ $pendingBreakdown['awaiting_approval'] ?? 0 }} | {{ $pendingBreakdown['in_progress'] ?? 0 }} | {{ $pendingBreakdown['planned_later'] ?? 0 }} | {{ $pendingBreakdown['unscheduled_backlog'] ?? 0 }} |
</x-mail::table>
@endif

@if ($breakdown->isEmpty())
No tasks completed today.
@else
## Completed today
@foreach ($breakdown as $name => $tasks)
**{{ $name }}**
@foreach ($tasks as $task)
- [{{ $task['label'] }}]({{ $task['url'] }})
@endforeach

@endforeach
@endif

@if ($reopenedToday->isNotEmpty())
## Reopened today
@foreach ($reopenedToday as $name => $tasks)
**{{ $name }}**
@foreach ($tasks as $task)
- [{{ $task['label'] }}]({{ $task['url'] }})
@endforeach

@endforeach
@endif

@if ($progressNotes->isNotEmpty())
## Progress notes
@foreach ($progressNotes as $entry)
**[{{ $entry['title'] }}]({{ $entry['url'] }})**
@foreach ($entry['lines'] as $line)
- {{ $line }}
@endforeach

@endforeach
@endif

@if ($comments->isNotEmpty())
## Today's comments
@foreach ($comments as $entry)
**[{{ $entry['title'] }}]({{ $entry['url'] }})**
@foreach ($entry['lines'] as $line)
- {{ $line }}
@endforeach

@endforeach
@endif

@if (! empty($completeness))
## Team activity

{{ $completeness['members_with_activity'] }} of {{ $completeness['active_members'] }} active members have recorded activity today ({{ $completeness['missing_activity'] }} with none yet); {{ $completeness['tasks_updated_today'] }} tasks touched.
@endif

<x-mail::button :url="route('dashboards.department', ['department_id' => $department->id])">
View department dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
