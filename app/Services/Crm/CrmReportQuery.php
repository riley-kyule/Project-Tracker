<?php

namespace App\Services\Crm;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * SCAFFOLD ONLY — no CRM system is integrated with EWMS yet (confirmed: no
 * crm_* table/dataset exists anywhere in BigQuery, and no CRM API
 * credentials are configured). This exists purely so the Customer Service
 * "My Reports" flow has the same shape as the Marketing flow (GA4/GSC/
 * Ahrefs) and degrades the same way Ahrefs does — every method throws until
 * a real CRM data source is wired in, and callers must treat it as an
 * optional, independently-failing source.
 */
class CrmReportQuery
{
    public function isConfigured(): bool
    {
        return false;
    }

    /** @return array{active_profiles: int, expiring_profiles: int, sales: float} */
    public function summary(array $domains, Carbon $from, Carbon $to): array
    {
        throw new RuntimeException('CRM integration is not yet configured — no active profiles, expiring profiles, or sales data source is connected.');
    }
}
