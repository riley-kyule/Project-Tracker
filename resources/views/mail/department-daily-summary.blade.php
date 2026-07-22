<x-mail::message>
# {{ $department->name }} — daily summary

**{{ $completedToday }}** tasks completed today, **{{ $pending }}** still pending.

<x-mail::button :url="route('dashboards.department', ['department_id' => $department->id])">
View department dashboard
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
