<?php

namespace Database\Seeders;

use App\Models\SeoTaskTemplate;
use Illuminate\Database\Seeder;

/**
 * The standard SEO Board item library — SEO Board Requirements Specification
 * v1.1 §4.1/§4.3/§4.4/§4.5/§4.6 (daily) and §5.1/§5.2 (weekly). Fixed-weight
 * "mandatory" items match the spec's exact point values; "production" items
 * carry the spec's min/max band and default to its band midpoint — the HOD
 * adjusts the actual weight per assignment so each day's/week's card totals 100.
 */
class SeoTaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->daily() as $item) {
            SeoTaskTemplate::query()->firstOrCreate(
                ['card_type' => SeoTaskTemplate::CARD_TYPE_DAILY, 'name' => $item['name']],
                $item,
            );
        }

        foreach ($this->weekly() as $item) {
            SeoTaskTemplate::query()->firstOrCreate(
                ['card_type' => SeoTaskTemplate::CARD_TYPE_WEEKLY, 'name' => $item['name']],
                $item,
            );
        }
    }

    /** @return list<array<string, mixed>> */
    private function daily(): array
    {
        $mandatory = fn (string $section, string $name, int $weight, string $criteria, int $position) => [
            'card_type' => SeoTaskTemplate::CARD_TYPE_DAILY,
            'section' => $section,
            'name' => $name,
            'classification' => SeoTaskTemplate::CLASSIFICATION_MANDATORY,
            'default_weight' => $weight,
            'min_weight' => $weight,
            'max_weight' => $weight,
            'requires_quantity' => false,
            'evidence_required' => true,
            'evidence_type' => 'system_record',
            'completion_criteria' => $criteria,
            'is_active' => true,
            'position' => $position,
        ];

        $production = fn (string $name, int $default, int $min, int $max, string $evidence, int $position) => [
            'card_type' => SeoTaskTemplate::CARD_TYPE_DAILY,
            'section' => 'production',
            'name' => $name,
            'classification' => SeoTaskTemplate::CLASSIFICATION_PRODUCTION,
            'default_weight' => $default,
            'min_weight' => $min,
            'max_weight' => $max,
            'requires_quantity' => true,
            'evidence_required' => true,
            'evidence_type' => 'url',
            'completion_criteria' => $evidence,
            'is_active' => true,
            'position' => $position,
        ];

        return [
            // §4.3 Monitoring and controls — 15 points, fixed.
            $mandatory('monitoring', 'Site availability check', 3, 'All assigned sites checked; any outage recorded and escalated.', 1),
            $mandatory('monitoring', 'Site health and security check', 4, 'Required health/security signals checked; anomalies recorded with evidence.', 2),
            $mandatory('monitoring', 'Ranking and SERP monitoring', 4, 'Required keyword set reviewed; material movement recorded.', 3),
            $mandatory('monitoring', 'Traffic performance monitoring', 4, 'GA4 and GSC reviewed; material changes or anomalies recorded.', 4),

            // §4.4 Assigned production work library — HOD allocates 65 points total among selected items.
            $production('Content execution', 17, 10, 25, 'Document or URL, scope completed and submission reference.', 10),
            $production('On-page optimisation', 13, 8, 18, 'URLs and the fields or elements changed.', 11),
            $production('Improve underperforming landing pages', 17, 10, 25, 'Diagnosis, URL, implemented change and reason.', 12),
            $production('Keyword opportunity research', 10, 5, 15, 'Keyword set, intent, target page and recommended action.', 13),
            $production('Cannibalisation analysis and resolution', 14, 8, 20, 'Conflicting URLs, decision and implemented or assigned fix.', 14),
            $production('GSC error resolution', 12, 5, 20, 'Error, affected URLs, action and verification/escalation.', 15),
            $production('Ahrefs issue resolution', 12, 5, 20, 'Issue, affected URLs, action and verification/escalation.', 16),
            $production('Internal link implementation', 10, 5, 15, 'Source and destination URLs plus link count.', 17),
            $production('Outreach or external link work', 10, 5, 15, 'Verified outreach/placement evidence; raw messages alone may not equal a successful placement.', 18),
            $production('Exotic network link implementation', 10, 5, 15, 'Source and destination URLs and relevance.', 19),

            // §4.5 Implementation and follow-up — 10 points, fixed.
            $mandatory('implementation', 'Implementation and follow-up', 10, 'Identify the earlier task, owner and expected result. Must not duplicate routine production work.', 20),

            // §4.6 Documentation and escalation — 5 + 5 points, fixed.
            $mandatory('documentation', 'Document completed work', 5, 'Evidence attached to the relevant checklist item, not only a general comment.', 30),
            $mandatory('documentation', 'Report issues and blockers', 5, 'Blocker, time identified, escalation recipient and supporting evidence required. If no blocker exists, these points transfer to documentation/closure quality.', 31),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function weekly(): array
    {
        $item = fn (string $section, string $name, int $weight, string $criteria, bool $quantity, int $position) => [
            'card_type' => SeoTaskTemplate::CARD_TYPE_WEEKLY,
            'section' => $section,
            'name' => $name,
            'classification' => SeoTaskTemplate::CLASSIFICATION_MANDATORY,
            'default_weight' => $weight,
            'min_weight' => $weight,
            'max_weight' => $weight,
            'requires_quantity' => $quantity,
            'evidence_required' => true,
            'evidence_type' => 'report',
            'completion_criteria' => $criteria,
            'is_active' => true,
            'position' => $position,
        ];

        return [
            // §5.1/§5.2 — 100 points, fixed weights.
            $item('deliverables', 'Complete planned content and on-page deliverables', 40, 'HOD-set target quantity, assigned URLs/pages and acceptance criteria; score proportional to accepted target output.', true, 1),
            $item('technical', 'Complete planned technical SEO actions', 20, 'HOD-set named issues or target work queue; valid escalations may receive credit where resolution depends on another owner.', true, 2),
            $item('authority', 'Complete authority and linking targets', 15, 'Separate internal, external and network targets; quality and relevance mandatory.', true, 3),
            $item('research', 'Submit actionable keyword/competitor insight', 15, 'Minimum one useful, evidenced recommendation or HOD-defined research target; a generic narrative earns no credit.', false, 4),
            $item('closure', 'Submit weekly closure', 10, 'Completed work, evidence, unfinished items, blockers, lessons and next-week actions.', false, 5),
        ];
    }
}
