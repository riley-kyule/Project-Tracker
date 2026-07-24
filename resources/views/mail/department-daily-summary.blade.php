<x-mail::message>
# {{ $department->name }} — daily summary

**{{ $completedToday }}** tasks completed today, **{{ $pending }}** still pending.

@if ($breakdown->isEmpty())
No tasks completed today.
@else
<x-mail::table>
| Member | Tasks completed |
| :--- | :--- |
@foreach ($breakdown as $name => $titles)
| {{ $name }} | {{ $titles->implode('; ') }} |
@endforeach
</x-mail::table>
@endif

<x-mail::button :url="route('dashboards.department', ['department_id' => $department->id])">
View department dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
