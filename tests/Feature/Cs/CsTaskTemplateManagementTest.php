<?php

namespace Tests\Feature\Cs;

use App\Models\CompanySetting;
use App\Models\CsTaskTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The template library's create/edit/deactivate/reactivate workflow, and who may run it. */
class CsTaskTemplateManagementTest extends TestCase
{
    use CsFixtures, RefreshDatabase;

    private function payload(array $over = []): array
    {
        return [
            'card_type' => 'daily', 'section' => 'extra', 'name' => 'Weekend cover check', 'classification' => 'mandatory',
            'default_weight' => 5, 'min_weight' => 5, 'max_weight' => 5, 'requires_quantity' => false, 'evidence_required' => true, ...$over,
        ];
    }

    public function test_the_hod_can_create_edit_deactivate_and_reactivate_a_template(): void
    {
        [$hod] = $this->csLeaders();

        $this->actingAs($hod)->post('/cs-board/templates', $this->payload())->assertRedirect();
        $template = CsTaskTemplate::query()->where('name', 'Weekend cover check')->firstOrFail();

        $this->actingAs($hod)->patch("/cs-board/templates/{$template->id}", $this->payload(['name' => 'Weekend cover', 'default_weight' => 6, 'min_weight' => 6, 'max_weight' => 6]))->assertRedirect();
        $this->assertSame('Weekend cover', $template->fresh()->name);

        $this->actingAs($hod)->delete("/cs-board/templates/{$template->id}")->assertRedirect();
        $this->assertFalse($template->fresh()->is_active);

        $this->actingAs($hod)->patch("/cs-board/templates/{$template->id}", $this->payload(['name' => 'Weekend cover', 'is_active' => true]))->assertRedirect();
        $this->assertTrue($template->fresh()->is_active);
    }

    public function test_the_settings_page_opens_for_the_hod_and_only_for_cs_leadership(): void
    {
        [$hod, $assistant] = $this->csLeaders();
        [$member] = $this->csMember();

        $this->actingAs($hod)->get('/cs-board/settings')->assertOk();
        $this->actingAs($member)->get('/cs-board/settings')->assertForbidden();
        $this->actingAs($this->marketingManagerWithCsPermissions())->get('/cs-board/settings')->assertForbidden();
        $this->assertFalse($assistant->can('cs.templates.manage'));
    }

    public function test_members_and_other_departments_managers_cannot_change_templates(): void
    {
        $this->csLeaders();
        [$member] = $this->csMember();
        $template = CsTaskTemplate::query()->firstOrFail();

        foreach ([$member, $this->marketingManagerWithCsPermissions()] as $user) {
            $this->actingAs($user)->post('/cs-board/templates', $this->payload())->assertForbidden();
            $this->actingAs($user)->patch("/cs-board/templates/{$template->id}", $this->payload())->assertForbidden();
            $this->actingAs($user)->delete("/cs-board/templates/{$template->id}")->assertForbidden();
        }

        $this->assertTrue($template->fresh()->is_active);
    }

    /**
     * cs.settings.manage (unlike cs.templates.manage) is not granted to
     * Department Manager by default — same as SEO's seo.settings.manage, see
     * SeoNotificationTest — so board settings are CEO/Administrator
     * territory out of the box, and only reach an HOD if that permission is
     * separately granted through the /admin/permissions matrix.
     */
    public function test_the_ceo_can_edit_response_time_standards_currency_and_calibration_date(): void
    {
        $this->csLeaders();
        $ceo = User::factory()->create()->assignRole('CEO');

        $payload = [
            'cs_reporting_currency' => 'usd',
            'cs_response_time_standards' => ['chat' => 3, 'call' => 10, 'email' => 45, 'other' => 30],
            'cs_calibration_ends_at' => now()->addWeeks(4)->toDateString(),
        ];

        $this->actingAs($ceo)->post('/cs-board/settings/board', $payload)->assertRedirect();

        $setting = CompanySetting::current();
        $this->assertSame('USD', $setting->cs_reporting_currency);
        $this->assertSame(['chat' => 3, 'call' => 10, 'email' => 45, 'other' => 30], $setting->cs_response_time_standards);
        $this->assertSame($payload['cs_calibration_ends_at'], $setting->cs_calibration_ends_at->toDateString());
    }

    public function test_a_plain_hod_members_and_other_departments_managers_cannot_edit_board_settings(): void
    {
        [$hod] = $this->csLeaders();
        [$member] = $this->csMember();
        $before = CompanySetting::current()->cs_reporting_currency;

        $payload = [
            'cs_reporting_currency' => 'usd',
            'cs_response_time_standards' => ['chat' => 1, 'call' => 1, 'email' => 1, 'other' => 1],
        ];

        foreach ([$hod, $member, $this->marketingManagerWithCsPermissions()] as $user) {
            $this->actingAs($user)->post('/cs-board/settings/board', $payload)->assertForbidden();
        }

        $this->assertSame($before, CompanySetting::current()->cs_reporting_currency);
    }
}
