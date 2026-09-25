<x-mail::message>
# Customer Service daily card — {{ $payload['employee_name'] }}

**{{ $payload['department'] }}** · {{ $payload['role'] ?? 'Customer Service' }} · {{ $payload['work_date'] }} · Card #{{ $payload['card_id'] }} · closed {{ $payload['closed_at'] }}

## Score summary

- Planned points: **{{ $payload['planned_points'] }}**
- Employee-submitted (provisional): **{{ $payload['employee_submitted_points'] ?? '—' }}**
- Approved points: **{{ $payload['approved_points'] ?? 'pending HOD review' }}**
- Quota achievement: **{{ $payload['quota_percentage'] !== null ? $payload['quota_percentage'].'%' : '—' }}**

@if ($payload['approved_points'] === null)
Approval is still pending — this snapshot reflects the employee's own submission only. The dashboard and any later report will update once the HOD decides each item.
@endif

@if (! empty($payload['commercial']))
## Commercial activity this week

- New paying customers: **{{ $payload['commercial']['new_customers'] }}** · revenue {{ $payload['commercial']['currency'] }} {{ number_format($payload['commercial']['new_customer_revenue'], 2) }}
- Renewed/reactivated customers: **{{ $payload['commercial']['renewed_customers'] }}** · revenue {{ $payload['commercial']['currency'] }} {{ number_format($payload['commercial']['retained_revenue'], 2) }}
@endif

@if (! empty($payload['kanban']))
## Kanban activity

@php($k = $payload['kanban'])
Created **{{ $k['counts']['created'] }}** · moved **{{ $k['counts']['moved'] }}** · completed **{{ $k['counts']['completed'] }}** · blocked **{{ $k['counts']['blocked'] }}** · overdue **{{ $k['counts']['overdue'] }}**

@foreach (['completed' => 'Completed', 'created' => 'Created', 'moved' => 'Moved', 'blocked' => 'Blocked now', 'overdue' => 'Overdue now'] as $key => $label)
@if (! empty($k[$key]))
**{{ $label }}**
@foreach ($k[$key] as $task)
- [{{ $task['title'] }}]({{ $task['url'] }})
@endforeach

@endif
@endforeach
@endif

## Item details

@foreach ($payload['items'] as $item)
**{{ $item['name'] }}** ({{ $item['section'] }}, weight {{ $item['weight'] }})
- Employee status: {{ $item['employee_status'] }}{{ $item['submitted_at'] ? ' at '.$item['submitted_at'] : '' }}
@if ($item['target_quantity'] !== null)
- Quantity: {{ $item['achieved_quantity'] ?? 0 }} / {{ $item['target_quantity'] }}
@endif
- HOD decision: {{ $item['hod_decision'] ?? 'pending review' }}
@if (! empty($item['evidence']))
- Evidence:
@foreach ($item['evidence'] as $file)
  - [{{ $file['name'] }}]({{ $file['url'] }})
@endforeach
@else
- Evidence attached: {{ $item['evidence_count'] }}
@endif

@endforeach

@if (! empty($payload['exceptions']))
## Exceptions

@foreach ($payload['exceptions'] as $exception)
- **{{ $exception['name'] }}** — {{ $exception['employee_status'] }}{{ $exception['hod_decision'] ? ', '.$exception['hod_decision'] : '' }}{{ $exception['reason'] ? ': '.$exception['reason'] : '' }}
@endforeach
@endif

@if (! empty($payload['audit_summary']))
## Changes during the day

@foreach ($payload['audit_summary'] as $line)
- {{ $line }}
@endforeach
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
