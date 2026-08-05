<x-mail::message>
# Daily summary — {{ now()->format('M j, Y') }}

**{{ $totalCompletedToday }}** tasks completed today across the company, **{{ $totalPending }}** still pending.

@foreach ($departments as $department)
- **{{ $department['name'] }}**: {{ $department['completed_today'] }} completed today, {{ $department['pending'] }} pending ({{ $department['pending_breakdown']['overdue'] ?? 0 }} overdue)
@endforeach

@foreach ($departments as $department)
@if ($department['breakdown']->isNotEmpty() || $department['comments']->isNotEmpty() || $department['progress_notes']->isNotEmpty() || $department['reopened_today']->isNotEmpty())
## {{ $department['name'] }}

@if ($department['breakdown']->isNotEmpty())
**Completed today**
@foreach ($department['breakdown'] as $name => $tasks)
{{ $name }}:
@foreach ($tasks as $task)
@include('mail.partials.task-line', ['task' => $task])
@endforeach

@endforeach
@endif

@if ($department['reopened_today']->isNotEmpty())
**Reopened today**
@foreach ($department['reopened_today'] as $name => $tasks)
{{ $name }}:
@foreach ($tasks as $task)
@include('mail.partials.task-line', ['task' => $task])
@endforeach

@endforeach
@endif

@if ($department['progress_notes']->isNotEmpty())
**Progress notes**
@foreach ($department['progress_notes'] as $entry)
[{{ $entry['title'] }}]({{ $entry['url'] }}):
@foreach ($entry['lines'] as $line)
- {{ $line }}
@endforeach

@endforeach
@endif

@if ($department['comments']->isNotEmpty())
**Today's comments**
@foreach ($department['comments'] as $entry)
[{{ $entry['title'] }}]({{ $entry['url'] }}):
@foreach ($entry['lines'] as $line)
- {{ $line }}
@endforeach

@endforeach
@endif

@if (! empty($department['completeness']))
{{ $department['completeness']['members_with_activity'] }} of {{ $department['completeness']['active_members'] }} active members have recorded activity today.
@endif
@endif
@endforeach

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
