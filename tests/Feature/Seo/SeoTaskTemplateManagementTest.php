<?php

namespace Tests\Feature\Seo;

use App\Models\SeoTaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The task template library's actual create/edit/deactivate/reactivate
 * workflow — the backend has always supported all four, only the UI for
 * add/edit was missing until now.
 */
class SeoTaskTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create()->assignRole('Administrator');
    }

    public function test_a_new_production_template_can_be_created(): void
    {
        $payload = [
            'card_type' => 'daily', 'section' => 'production', 'name' => 'Schema markup rollout',
            'classification' => 'production', 'default_weight' => 12, 'min_weight' => 8, 'max_weight' => 20,
            'requires_quantity' => true, 'quantity_unit' => 'pages', 'evidence_type' => 'url',
            'evidence_required' => true, 'completion_criteria' => 'Structured data validated on each page.',
        ];

        $this->actingAs($this->admin())->post('/seo-board/templates', $payload)->assertRedirect();

        $this->assertDatabaseHas('seo_task_templates', ['name' => 'Schema markup rollout', 'min_weight' => 8, 'max_weight' => 20]);
    }

    /**
     * The system was already safe for this: SeoDailyCardController::assignItems
     * recomputes each card's fixed-weight total from whatever mandatory items
     * actually exist, not a hardcoded 35 — so editing a mandatory template's
     * weight is a real, supported operation, not just something the backend
     * happens to allow by omission.
     */
    public function test_a_mandatory_templates_weight_can_be_edited(): void
    {
        $template = SeoTaskTemplate::query()->where('classification', 'mandatory')->where('name', 'Site availability check')->firstOrFail();

        $payload = [
            'card_type' => $template->card_type, 'section' => $template->section, 'name' => $template->name,
            'classification' => 'mandatory', 'default_weight' => 5, 'min_weight' => 5, 'max_weight' => 5,
            'requires_quantity' => false, 'evidence_required' => true, 'completion_criteria' => $template->completion_criteria,
        ];

        $this->actingAs($this->admin())->patch("/seo-board/templates/{$template->id}", $payload)->assertRedirect();

        $this->assertSame(5.0, (float) $template->fresh()->default_weight);
    }

    public function test_a_template_can_be_deactivated_and_reactivated(): void
    {
        $template = SeoTaskTemplate::query()->create([
            'card_type' => 'daily', 'section' => 'production', 'name' => 'Toggle target',
            'classification' => 'production', 'default_weight' => 10, 'min_weight' => 5, 'max_weight' => 15,
            'requires_quantity' => false, 'evidence_required' => false, 'is_active' => true,
        ]);

        $this->actingAs($this->admin())->delete("/seo-board/templates/{$template->id}")->assertRedirect();
        $this->assertFalse($template->fresh()->is_active);

        $payload = [
            'card_type' => $template->card_type, 'section' => $template->section, 'name' => $template->name,
            'classification' => $template->classification, 'default_weight' => $template->default_weight,
            'min_weight' => $template->min_weight, 'max_weight' => $template->max_weight,
            'requires_quantity' => $template->requires_quantity, 'evidence_required' => $template->evidence_required,
            'is_active' => true,
        ];

        $this->actingAs($this->admin())->patch("/seo-board/templates/{$template->id}", $payload)->assertRedirect();
        $this->assertTrue($template->fresh()->is_active);
    }

    public function test_a_marketing_role_hod_cannot_manage_templates_without_the_permission(): void
    {
        $user = User::factory()->create()->assignRole('Customer Service');

        $this->actingAs($user)->post('/seo-board/templates', [
            'card_type' => 'daily', 'section' => 'production', 'name' => 'Should not save',
            'classification' => 'production', 'default_weight' => 10, 'min_weight' => 5, 'max_weight' => 15,
            'requires_quantity' => false, 'evidence_required' => false,
        ])->assertForbidden();
    }
}
