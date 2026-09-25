<?php

namespace App\Services\Cs;

use App\Models\CompanySetting;
use App\Models\CsSalesRecord;
use App\Models\CsWeeklyTarget;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * The commercial ledger's integrity rules — Customer Service Board
 * Requirements Specification v1.0 §4 "Non duplication and attribution
 * rules". Kept separate from CsScoringService (which owns card/item
 * scoring) because sale attribution is a distinct concern: it governs
 * whether a sale is allowed to exist at all, not how many points it earns.
 */
class CsSalesAttributionService
{
    public function __construct(private readonly CsExchangeRateService $exchangeRates) {}

    /**
     * Records a claimed sale — pending until an HOD clears it. Rejects a
     * 'new' record for a customer who already has a cleared 'new' record
     * anywhere, per §4 "Existing customer: renewals and reactivations are
     * reported separately from genuinely new customers" — once new, always
     * existing.
     */
    public function record(Employee $employee, array $data, User $actor): CsSalesRecord
    {
        if ($data['category'] === CsSalesRecord::CATEGORY_NEW) {
            $alreadyNew = CsSalesRecord::query()
                ->where('customer_identifier', $data['customer_identifier'])
                ->where('category', CsSalesRecord::CATEGORY_NEW)
                ->where('status', CsSalesRecord::STATUS_CLEARED)
                ->exists();

            if ($alreadyNew) {
                throw new InvalidArgumentException('This customer already has a cleared "new customer" sale — record this as a renewal or reactivation instead.');
            }
        }

        $conversion = $this->convert($data['currency'], (float) $data['amount']);

        $record = CsSalesRecord::query()->create([
            ...$data,
            ...$conversion,
            'employee_id' => $employee->id,
            'status' => CsSalesRecord::STATUS_PENDING,
            'recorded_by' => $actor->id,
        ]);

        AuditLogger::log($record, 'cs_sales_record.recorded', [], $record->only([
            'category', 'customer_identifier', 'payment_reference', 'amount', 'currency', 'week_start_date',
        ]));

        return $record;
    }

    /**
     * HOD confirms the payment cleared and the attribution is correct — §4
     * "Cleared payment: a sale counts only after payment is confirmed and
     * the correct service is activated" and "Sales ownership: EWMS stores
     * ... the approved attribution decision". The only path that can ever
     * move a record into the counted set.
     */
    public function clear(CsSalesRecord $record, User $actor, ?array $attribution = null): CsSalesRecord
    {
        $old = $record->only(['status', 'attribution_type', 'shared_with_employee_id', 'split_percentage']);

        $record->status = CsSalesRecord::STATUS_CLEARED;
        $record->cleared_at = now();
        $record->approved_by = $actor->id;
        $record->approved_at = now();

        if ($attribution !== null) {
            $record->attribution_type = $attribution['attribution_type'] ?? CsSalesRecord::ATTRIBUTION_PRIMARY;
            $record->shared_with_employee_id = $attribution['shared_with_employee_id'] ?? null;
            $record->split_percentage = $attribution['split_percentage'] ?? null;
        }

        $record->save();

        AuditLogger::log($record, 'cs_sales_record.cleared', $old, $record->only([
            'status', 'attribution_type', 'shared_with_employee_id', 'split_percentage',
        ]));

        return $record;
    }

    /** Reversed, refunded or found fraudulent after the fact — §4 "Cleared payment": these never count as collected revenue once flagged. */
    public function flag(CsSalesRecord $record, string $status, string $reason, User $actor): CsSalesRecord
    {
        if (! in_array($status, [CsSalesRecord::STATUS_REVERSED, CsSalesRecord::STATUS_REFUNDED, CsSalesRecord::STATUS_FRAUDULENT], true)) {
            throw new InvalidArgumentException("Unknown flag status: {$status}");
        }

        $old = $record->only(['status']);
        $record->status = $status;
        $record->save();

        AuditLogger::log($record, 'cs_sales_record.flagged', $old, ['status' => $status, 'reason' => $reason]);

        return $record;
    }

    /**
     * §3 "Target changes after the period begins require a reason and audit
     * record". Creates the week's target if it doesn't exist yet (before the
     * period begins — no reason needed), or updates it with a mandatory
     * reason once the week has started.
     */
    public function setWeeklyTarget(Employee $employee, Carbon $weekStart, array $targets, User $actor, ?string $reason = null): CsWeeklyTarget
    {
        $weekStart = $weekStart->copy()->startOfWeek();

        $existing = CsWeeklyTarget::query()
            ->where('employee_id', $employee->id)
            ->whereDate('week_start_date', $weekStart->toDateString())
            ->first();

        $periodStarted = $weekStart->isPast() || $weekStart->isToday();

        if ($existing !== null && $periodStarted && (trim($reason ?? '') === '')) {
            throw new InvalidArgumentException('A reason is required to change targets after the week has begun.');
        }

        $attributes = [
            'new_customers_target' => $targets['new_customers_target'],
            'new_customer_revenue_target' => $targets['new_customer_revenue_target'],
            'renewed_customers_target' => $targets['renewed_customers_target'],
            'retained_revenue_target' => $targets['retained_revenue_target'],
            'currency' => $targets['currency'],
            'set_by' => $actor->id,
            'set_at' => now(),
        ];

        if ($existing !== null) {
            $old = $existing->only(array_keys($attributes));
            $existing->update($attributes);

            AuditLogger::log($existing, 'cs_weekly_target.updated', $old, [...$existing->only(array_keys($attributes)), 'reason' => $reason]);

            return $existing;
        }

        $target = CsWeeklyTarget::query()->create([
            'employee_id' => $employee->id,
            'week_start_date' => $weekStart->toDateString(),
            'week_end_date' => $weekStart->copy()->endOfWeek()->toDateString(),
            ...$attributes,
        ]);

        AuditLogger::log($target, 'cs_weekly_target.set', [], $target->only(array_keys($attributes)));

        return $target;
    }

    /**
     * §4 "Currency": revenue is stored in transaction currency and the
     * company reporting currency using the approved exchange-rate source
     * and date. Same currency as the reporting currency needs no lookup;
     * no stored rate yet for a brand-new currency leaves the converted
     * fields null rather than blocking the sale from being recorded at all.
     *
     * @return array{reporting_currency_amount: ?float, exchange_rate: ?float, exchange_rate_date: ?string}
     */
    private function convert(string $currency, float $amount): array
    {
        $reportingCurrency = CompanySetting::current()->cs_reporting_currency ?? 'KES';

        if (strtoupper($currency) === strtoupper($reportingCurrency)) {
            return ['reporting_currency_amount' => $amount, 'exchange_rate' => 1.0, 'exchange_rate_date' => now()->toDateString()];
        }

        $rate = $this->exchangeRates->rateOn($currency, now()->toDateString());

        if ($rate === null) {
            return ['reporting_currency_amount' => null, 'exchange_rate' => null, 'exchange_rate_date' => null];
        }

        return [
            'reporting_currency_amount' => round($amount * (float) $rate->rate_to_reporting_currency, 2),
            'exchange_rate' => (float) $rate->rate_to_reporting_currency,
            'exchange_rate_date' => $rate->rate_date->toDateString(),
        ];
    }
}
