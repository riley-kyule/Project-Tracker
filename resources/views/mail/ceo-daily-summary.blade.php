<x-mail::message>
# Daily summary — {{ now()->format('M j, Y') }}

**{{ $totalCompletedToday }}** tasks completed today across the company, **{{ $totalPending }}** still pending.

<x-mail::table>
| Department | Completed today | Pending | Overdue |
| :--- | ---: | ---: | ---: |
@foreach ($departments as $department)
| {{ $department['name'] }} | {{ $department['completed_today'] }} | {{ $department['pending'] }} | {{ $department['pending_breakdown']['overdue'] ?? 0 }} |
@endforeach
</x-mail::table>

@foreach ($departments as $department)
@if ($department['breakdown']->isNotEmpty() || $department['comments']->isNotEmpty() || $department['progress_notes']->isNotEmpty() || $department['reopened_today']->isNotEmpty())
## {{ $department['name'] }}

@if ($department['breakdown']->isNotEmpty())
<x-mail::table>
| Member | Tasks completed |
| :--- | :--- |
@foreach ($department['breakdown'] as $name => $titles)
| {{ $name }} | {{ $titles->implode('; ') }} |
@endforeach
</x-mail::table>
@endif

@if ($department['reopened_today']->isNotEmpty())
**Reopened today**

<x-mail::table>
| Member | Tasks reopened |
| :--- | :--- |
@foreach ($department['reopened_today'] as $name => $titles)
| {{ $name }} | {{ $titles->implode('; ') }} |
@endforeach
</x-mail::table>
@endif

@if ($department['progress_notes']->isNotEmpty())
**Progress notes**

<x-mail::table>
| Task | Notes |
| :--- | :--- |
@foreach ($department['progress_notes'] as $title => $lines)
| {{ $title }} | {{ $lines->implode('; ') }} |
@endforeach
</x-mail::table>
@endif

@if ($department['comments']->isNotEmpty())
**Today's comments**

<x-mail::table>
| Task | Comments |
| :--- | :--- |
@foreach ($department['comments'] as $title => $lines)
| {{ $title }} | {{ $lines->implode('; ') }} |
@endforeach
</x-mail::table>
@endif

@if (! empty($department['completeness']))
{{ $department['completeness']['members_with_activity'] }} of {{ $department['completeness']['active_members'] }} active members have recorded activity today.
@endif
@endif
@endforeach

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
