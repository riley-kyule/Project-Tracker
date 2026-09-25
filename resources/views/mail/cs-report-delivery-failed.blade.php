<x-mail::message>
# Customer Service daily card report delivery failed

**{{ $payload['employee_name'] ?? 'Unknown employee' }}** · {{ $payload['department'] ?? '—' }} · {{ $payload['work_date'] ?? '—' }}

A copy of this report could not be delivered to **{{ $delivery->resolvedName() ?? $delivery->resolvedEmail() }}** after {{ $delivery->retry_count }} attempt(s).

- Last error: {{ $delivery->failure_reason ?? 'unknown' }}
- Last attempt: {{ $delivery->failed_at?->toDateTimeString() ?? '—' }}

The card's own data and score are unaffected — this only concerns the emailed copy. Check the recipient's email address and the System Report Log for details.

<x-mail::button :url="route('admin.report-deliveries.index')">
View System Report Log
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
