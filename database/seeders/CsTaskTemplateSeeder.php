<?php

namespace Database\Seeders;

use App\Models\CsTaskTemplate;
use Illuminate\Database\Seeder;

/**
 * The standard Customer Service Board item library — Customer Service Board
 * Requirements Specification v1.0 §5 (daily) and §6 (weekly). Unlike the SEO
 * Board, every daily responsibility here is a fixed-weight, fully specified
 * 100-point list (no separate HOD "assign today's production work" step —
 * see App\Services\Cs\CsCardLifecycleService::seedMandatoryItems()), so
 * every row below is seeded with min_weight === max_weight === default_weight.
 * Touches only cs_task_templates — no other table or board.
 */
class CsTaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->daily() as $item) {
            CsTaskTemplate::query()->firstOrCreate(
                ['card_type' => CsTaskTemplate::CARD_TYPE_DAILY, 'name' => $item['name']],
                $item,
            );
        }

        foreach ($this->weekly() as $item) {
            CsTaskTemplate::query()->firstOrCreate(
                ['card_type' => CsTaskTemplate::CARD_TYPE_WEEKLY, 'name' => $item['name']],
                $item,
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function daily(): array
    {
        $item = fn (string $section, string $name, int $weight, string $criteria, string $evidenceType, int $position) => [
            'card_type' => CsTaskTemplate::CARD_TYPE_DAILY,
            'section' => $section,
            'name' => $name,
            'classification' => CsTaskTemplate::CLASSIFICATION_MANDATORY,
            'default_weight' => $weight,
            'min_weight' => $weight,
            'max_weight' => $weight,
            'requires_quantity' => false,
            'evidence_required' => true,
            'evidence_type' => $evidenceType,
            'completion_criteria' => $criteria,
            'is_active' => true,
            'position' => $position,
        ];

        return [
            // §5 — 100 points, fixed weights.
            $item('queue_clearance', 'Morning queue clearance', 15, 'Review overnight enquiries, pending chats and unresolved requests within the required response time.', 'system_record', 1),
            $item('new_customers', 'New-customer acquisition', 25, 'Contact and follow up assigned or identified prospects and record each outcome in the CRM.', 'system_record', 2),
            $item('renewals', 'Renewals and reactivation', 20, 'Contact expiring, expired and inactive advertisers and record renewal status and next action.', 'system_record', 3),
            $item('sales_support', 'Sales and activation support', 15, 'Assist payment, registration and activation cases and verify correct service delivery.', 'document', 4),
            $item('continuity', 'Assigned-platform continuity', 15, 'Inspect assigned platforms and follow customer-facing or operational issues through escalation.', 'system_record', 5),
            $item('crm', 'CRM and documentation', 5, 'Maintain accurate customer status, contact outcome, evidence and next-action dates.', 'system_record', 6),
            $item('escalation', 'Escalation and daily closure', 5, 'Escalate blockers promptly and submit a complete daily closure record.', 'comment', 7),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function weekly(): array
    {
        $item = fn (string $section, string $name, int $weight, string $criteria, ?string $metricType, int $position) => [
            'card_type' => CsTaskTemplate::CARD_TYPE_WEEKLY,
            'section' => $section,
            'name' => $name,
            'classification' => CsTaskTemplate::CLASSIFICATION_MANDATORY,
            'default_weight' => $weight,
            'min_weight' => $weight,
            'max_weight' => $weight,
            // The three auto-calculated items (new sales, renewals, service
            // quality) never take a manually typed quantity — see
            // CsScoringService::decide() and CsServiceQualityService —
            // so requires_quantity stays false for them even though their
            // achievement is still quantity-shaped under the hood.
            'requires_quantity' => false,
            'metric_type' => $metricType,
            'evidence_required' => $metricType === null,
            'evidence_type' => 'report',
            'completion_criteria' => $criteria,
            'is_active' => true,
            'position' => $position,
        ];

        return [
            // §6 — 100 points, fixed weights.
            $item('new_sales', 'New-customer sales and revenue', 30, 'Achievement against both new paying-customer and new-customer revenue targets.', CsTaskTemplate::METRIC_NEW_SALES, 1),
            $item('renewals', 'Renewals reactivation and retained revenue', 25, 'Achievement against both customer and revenue targets for existing or expired advertisers.', CsTaskTemplate::METRIC_RENEWAL, 2),
            $item('follow_up', 'Follow-up and CRM pipeline management', 15, 'Required follow-ups completed on time with accurate stages, outcomes and next actions.', null, 3),
            $item('continuity', 'Platform continuity and issue closure', 15, 'Assigned platforms checked and material issues resolved, verified or properly escalated.', null, 4),
            $item('quality', 'Customer-service quality', 10, 'Response-time, resolution, complaint and quality standards achieved.', CsTaskTemplate::METRIC_SERVICE_QUALITY, 5),
            $item('closure', 'Weekly closure', 5, 'Accurate summary of results, exceptions, unresolved work and next-week actions.', null, 6),
        ];
    }
}
