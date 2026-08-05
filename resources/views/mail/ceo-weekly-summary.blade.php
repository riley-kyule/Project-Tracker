<x-mail::message>
# Company weekly summary — {{ now()->format('M j, Y') }}

**{{ $totalCompleted }}** tasks completed this week across the company.

@foreach ($departments as $department)
- **{{ $department['name'] }}**: {{ $department['completed_count'] }} completed this week ({{ $department['pending_breakdown']['overdue'] ?? 0 }} overdue)
@endforeach

@foreach ($departments as $department)
@if ($department['completed']->isNotEmpty())
## {{ $department['name'] }}
@foreach ($department['completed'] as $task)
@include('mail.partials.task-line', ['task' => $task])
@endforeach

@endif
@endforeach

<x-mail::button :url="route('dashboards.ceo')">
View CEO dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
