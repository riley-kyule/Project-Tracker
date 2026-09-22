<x-mail::message>
# SEO daily card — {{ $payload['employee_name'] }}

**{{ $payload['department'] }}** · {{ $payload['role'] ?? 'SEO' }} · {{ $payload['work_date'] }} · Card #{{ $payload['card_id'] }} · closed {{ $payload['closed_at'] }}

## Score summary

- Planned points: **{{ $payload['planned_points'] }}**
- Employee-submitted (provisional): **{{ $payload['employee_submitted_points'] ?? '—' }}**
- Approved points: **{{ $payload['approved_points'] ?? 'pending HOD review' }}**
- Quota achievement: **{{ $payload['quota_percentage'] !== null ? $payload['quota_percentage'].'%' : '—' }}**

@if ($payload['approved_points'] === null)
Approval is still pending — this snapshot reflects the employee's own submission only. The dashboard and any later report will update once the HOD decides each item.
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
