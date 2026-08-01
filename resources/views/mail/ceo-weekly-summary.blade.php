<x-mail::message>
# Company weekly summary — {{ now()->format('M j, Y') }}

**{{ $totalCompleted }}** tasks completed this week across the company.

<x-mail::table>
| Department | Completed this week | Overdue |
| :--- | ---: | ---: |
@foreach ($departments as $department)
| {{ $department['name'] }} | {{ $department['completed_count'] }} | {{ $department['pending_breakdown']['overdue'] ?? 0 }} |
@endforeach
</x-mail::table>

@foreach ($departments as $department)
@if ($department['completed']->isNotEmpty())
## {{ $department['name'] }}
@foreach ($department['completed'] as $task)
- [{{ $task['label'] }}]({{ $task['url'] }})
@endforeach

@endif
@endforeach

<x-mail::button :url="route('dashboards.ceo')">
View CEO dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
