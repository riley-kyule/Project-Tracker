<x-mail::message>
# Your weekly summary — {{ now()->format('M j, Y') }}

Hi {{ $recipient->name }}, here's how your week looked.

**{{ $completedCount }}** tasks completed this week.

@if ($completed->isNotEmpty())
@foreach ($completed as $task)
@include('mail.partials.task-line', ['task' => $task])
@endforeach
@endif

## Where things stand

@include('mail.partials.pending-breakdown', ['pendingBreakdown' => $pendingBreakdown])

<x-mail::button :url="route('dashboard')">
View my dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
