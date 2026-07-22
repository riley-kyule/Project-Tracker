<x-mail::message>
# Daily summary — {{ now()->format('M j, Y') }}

**{{ $totalCompletedToday }}** tasks completed today across the company, **{{ $totalPending }}** still pending.

<x-mail::table>
| Department | Completed today | Pending |
| :--- | ---: | ---: |
@foreach ($departments as $department)
| {{ $department['name'] }} | {{ $department['completed_today'] }} | {{ $department['pending'] }} |
@endforeach
</x-mail::table>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
