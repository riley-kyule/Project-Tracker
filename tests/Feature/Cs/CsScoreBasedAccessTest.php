<?php

namespace Tests\Feature\Cs;

use App\Models\Attachment;
use App\Models\Board;
use App\Models\CsCardItem;
use App\Models\CsDailyCard;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use App\Services\Cs\CsCardLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Score Based tab on the Customer Service Kanban board: who gets a
 * personal card view, who gets the team view, and who gets nothing.
 */
class CsScoreBasedAccessTest extends TestCase
{
    use CsFixtures, RefreshDatabase;

    private function companyVisibleCsBoard(): Board
    {
        return Board::factory()->create(['department_id' => $this->csDepartment()->id, 'visibility' => Board::VISIBILITY_COMPANY]);
    }

    public function test_cs_board_redirects_to_the_departments_kanban_board(): void
    {
        [$member] = $this->csMember();
        $board = $this->csBoard();

        $this->actingAs($member)->get('/cs-board')->assertRedirect("/boards/{$board->id}");
    }

    public function test_a_non_cs_user_holding_the_cs_role_permission_gets_no_card_and_no_tab(): void
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $user = User::factory()->create(['department_id' => $marketing->id])->assignRole('Customer Service');
        Employee::factory()->create(['user_id' => $user->id, 'department_id' => $this->csDepartment()->id]); // HR record says CS, the Departments page says Marketing
        $board = $this->companyVisibleCsBoard(); // company-visible, so this outsider can actually open it

        $this->assertTrue($user->can('cs.cards.view'), 'sanity check: holds the permission at the role level');
        $this->assertFalse($user->isCsEmployee());

        $this->actingAs($user)->get('/cs-board')->assertNotFound();

        $this->actingAs($user)->get("/boards/{$board->id}")->assertInertia(fn ($page) => $page
            ->where('scoreBoard', null)
            ->where('hodBoard', null));

        $this->assertDatabaseCount('cs_daily_cards', 0);
    }

    public function test_a_cs_member_gets_their_personal_card_and_todays_card_is_provisioned(): void
    {
        [$member, $employee] = $this->csMember();
        $board = $this->csBoard();

        $this->actingAs($member)->get("/boards/{$board->id}")->assertInertia(fn ($page) => $page
            ->where('scoreBoard.kind', 'cs')
            ->where('scoreBoard.data.employeeId', $employee->id)
            ->where('scoreBoard.data.dailyCard.status', 'open')
            ->where('scoreBoard.data.weeklyCard', null)
            ->has('scoreBoard.data.history')
            ->where('hodBoard', null));

        $card = CsDailyCard::query()->where('employee_id', $employee->id)->firstOrFail();
        $this->assertCount(7, $card->items);
        $this->assertEqualsWithDelta(100.0, (float) $card->items->sum('weight'), 0.01);
    }

    public function test_hod_assistant_ceo_and_admin_get_the_team_view_and_no_personal_card(): void
    {
        [$hod, $assistant] = $this->csLeaders();
        [, $memberOne] = $this->csMember();
        [, $memberTwo] = $this->csMember();
        $ceo = User::factory()->create()->assignRole('CEO');
        $admin = User::factory()->create()->assignRole('Administrator');
        $board = $this->csBoard();

        foreach ([$hod, $assistant, $ceo, $admin] as $leader) {
            $response = $this->actingAs($leader)->get("/boards/{$board->id}");

            $response->assertInertia(fn ($page) => $page
                ->where('scoreBoard', null)
                ->where('hodBoard.kind', 'cs')
                ->where('hodBoard.data.department.id', $this->csDepartment()->id)
                // Only the two scored members: the manager and assistant are in the department but never scored.
                ->has('hodBoard.data.employees', 2));

            $ids = collect($response->viewData('page')['props']['hodBoard']['data']['employees'])->pluck('id')->all();
            $this->assertEqualsCanonicalizing([$memberOne->id, $memberTwo->id], $ids);
        }

        // Visiting the board never creates a card for a manager, assistant, CEO or admin.
        $this->assertDatabaseMissing('cs_daily_cards', ['employee_id' => Employee::query()->where('user_id', $hod->id)->value('id')]);
        $this->assertDatabaseMissing('cs_daily_cards', ['employee_id' => Employee::query()->where('user_id', $assistant->id)->value('id')]);
    }

    public function test_leaders_are_redirected_from_cs_board_and_never_scored(): void
    {
        [$hod, $assistant] = $this->csLeaders();
        $board = $this->csBoard();

        $this->assertFalse($hod->isCsEmployee());
        $this->assertFalse($assistant->isCsEmployee());

        foreach ([$hod, $assistant] as $leader) {
            $this->actingAs($leader)->get('/cs-board')->assertRedirect("/boards/{$board->id}");
        }
    }

    public function test_a_member_only_ever_receives_their_own_cards_and_no_team_payload(): void
    {
        [$alice, $aliceEmployee] = $this->csMember();
        [, $bobEmployee] = $this->csMember();
        $board = $this->csBoard();

        $lifecycle = app(CsCardLifecycleService::class);
        $lifecycle->ensureTodaysCardsExist();
        $bobCard = CsDailyCard::query()->where('employee_id', $bobEmployee->id)->firstOrFail();
        $aliceCard = CsDailyCard::query()->where('employee_id', $aliceEmployee->id)->firstOrFail();

        $response = $this->actingAs($alice)->get("/boards/{$board->id}");

        $response->assertInertia(fn ($page) => $page
            ->where('scoreBoard.data.dailyCard.id', $aliceCard->id)
            ->where('hodBoard', null));

        $props = json_encode($response->viewData('page')['props']);
        $this->assertStringNotContainsString($bobEmployee->full_name, $props);

        $ownItemIds = $aliceCard->items->pluck('id')->all();
        $payloadItemIds = collect($response->viewData('page')['props']['scoreBoard']['data']['dailyCard']['items'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing($ownItemIds, $payloadItemIds);
        $this->assertEmpty(array_intersect($bobCard->items->pluck('id')->all(), $payloadItemIds));
    }

    public function test_a_member_cannot_touch_another_members_card_by_id(): void
    {
        [$alice] = $this->csMember();
        [, $bobEmployee] = $this->csMember();
        app(CsCardLifecycleService::class)->ensureTodaysCardsExist();

        $bobItem = CsCardItem::query()
            ->where('cardable_type', CsDailyCard::class)
            ->whereIn('cardable_id', CsDailyCard::query()->where('employee_id', $bobEmployee->id)->pluck('id'))
            ->firstOrFail();

        $this->actingAs($alice)->post("/cs-board/items/{$bobItem->id}/status", ['employee_status' => 'submitted'])->assertForbidden();
        $this->actingAs($alice)->post("/cs-board/items/{$bobItem->id}/decide", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($alice)->patch("/cs-board/items/{$bobItem->id}/weight", ['weight' => 5, 'reason' => 'x'])->assertForbidden();
        $this->actingAs($alice)->post("/cs-board/items/{$bobItem->id}/evidence")->assertForbidden();

        $attachment = $bobItem->evidence()->create([
            'uploaded_by' => User::factory()->create()->id, 'disk' => 'local', 'path' => 'attachments/cs-items/x.txt',
            'original_name' => 'x.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1, 'checksum' => str_repeat('a', 64),
        ]);
        $this->assertInstanceOf(Attachment::class, $attachment);
        $this->actingAs($alice)->get("/attachments/{$attachment->id}")->assertForbidden();

        $this->assertNull($bobItem->fresh()->hod_decision);
        $this->assertSame(CsCardItem::STATUS_NOT_STARTED, $bobItem->fresh()->employee_status);
    }

    public function test_a_member_cannot_use_any_leadership_endpoint(): void
    {
        [$alice, $aliceEmployee] = $this->csMember();
        [, $bobEmployee] = $this->csMember();
        $cs = $this->csDepartment();

        $this->actingAs($alice);

        $this->post("/cs-board/employees/{$bobEmployee->id}/weekly-target", [
            'week_start_date' => now()->startOfWeek()->toDateString(), 'new_customers_target' => 1, 'new_customer_revenue_target' => 1,
            'renewed_customers_target' => 1, 'retained_revenue_target' => 1, 'currency' => 'KES',
        ])->assertForbidden();
        $this->post("/cs-board/employees/{$aliceEmployee->id}/weekly-target", [
            'week_start_date' => now()->startOfWeek()->toDateString(), 'new_customers_target' => 999, 'new_customer_revenue_target' => 1,
            'renewed_customers_target' => 1, 'retained_revenue_target' => 1, 'currency' => 'KES',
        ])->assertForbidden(); // not even their own quota
        $this->post('/cs-board/platform-assignments', ['employee_id' => $aliceEmployee->id, 'country' => 'Kenya', 'effective_from' => today()->toDateString()])->assertForbidden();
        $this->post("/cs-board/employees/{$bobEmployee->id}/complaints", ['description' => 'x'])->assertForbidden();
        $this->post("/cs-board/employees/{$bobEmployee->id}/contact-quality-reviews", [])->assertForbidden();
        $this->post("/cs-board/hod/weekly/{$aliceEmployee->id}/".now()->startOfWeek()->toDateString().'/assign-items', ['items' => [['template_id' => 1]]])->assertForbidden();
        $this->post('/cs-board/templates', ['card_type' => 'daily', 'section' => 's', 'name' => 'n', 'classification' => 'mandatory', 'default_weight' => 5, 'min_weight' => 5, 'max_weight' => 5])->assertForbidden();
        $this->post("/cs-board/settings/notifications/{$cs->id}", ['email' => 'x@example.com'])->assertForbidden();
        $this->get('/cs-board/settings')->assertForbidden();

        $this->assertDatabaseCount('cs_weekly_targets', 0);
        $this->assertDatabaseCount('cs_weekly_cards', 0);
    }

    public function test_a_manager_of_another_department_has_no_authority_over_cs(): void
    {
        $marketingManager = $this->marketingManagerWithCsPermissions();
        [, $employee] = $this->csMember();
        app(CsCardLifecycleService::class)->ensureTodaysCardsExist();
        $item = CsCardItem::query()->where('cardable_type', CsDailyCard::class)->firstOrFail();
        $board = $this->companyVisibleCsBoard();

        $this->assertTrue($marketingManager->can('cs.cards.approve'), 'sanity check: holds the permission at the role level');

        $this->actingAs($marketingManager)->post("/cs-board/items/{$item->id}/decide", ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($marketingManager)->post("/cs-board/employees/{$employee->id}/complaints", ['description' => 'x'])->assertForbidden();
        $this->actingAs($marketingManager)->get('/cs-board/settings')->assertForbidden();
        $this->actingAs($marketingManager)->post('/cs-board/templates', ['card_type' => 'daily', 'section' => 's', 'name' => 'n', 'classification' => 'mandatory', 'default_weight' => 5, 'min_weight' => 5, 'max_weight' => 5])->assertForbidden();

        $this->actingAs($marketingManager)->get("/boards/{$board->id}")->assertInertia(fn ($page) => $page
            ->where('scoreBoard', null)
            ->where('hodBoard', null));
    }

    public function test_hod_and_assistant_can_decide_items_and_a_decision_needs_no_manager_role(): void
    {
        [$hod, $assistant] = $this->csLeaders();
        [, $employee] = $this->csMember();
        app(CsCardLifecycleService::class)->ensureTodaysCardsExist();

        $items = CsCardItem::query()->where('cardable_type', CsDailyCard::class)->orderBy('id')->get();

        $this->assertFalse($assistant->can('cs.cards.approve'), 'sanity check: the assistant holds no approve permission');

        $this->actingAs($hod)->post("/cs-board/items/{$items[0]->id}/decide", ['decision' => 'approved'])->assertRedirect();
        $this->actingAs($assistant)->post("/cs-board/items/{$items[1]->id}/decide", ['decision' => 'approved'])->assertRedirect();

        $this->assertNotNull($items[0]->fresh()->hod_decision);
        $this->assertNotNull($items[1]->fresh()->hod_decision);
        $this->assertSame($employee->id, CsDailyCard::query()->first()->employee_id);
    }

    /**
     * The two tabs must never disagree about who sees what: on Kanban a
     * member sees only their own tasks while leadership sees everyone's, and
     * the Score Based tab follows the same split on the very same request.
     */
    public function test_score_based_visibility_matches_kanban_visibility(): void
    {
        [$hod] = $this->csLeaders();
        [$alice] = $this->csMember();
        [$bob] = $this->csMember();
        $board = $this->csBoard();
        $column = $board->columns()->create(['name' => 'New', 'slug' => 'new', 'position' => 1, 'semantic_status' => 'idea']);

        $aliceTask = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id, 'department_id' => $board->department_id, 'created_by' => $hod->id, 'primary_assignee_id' => $alice->id]);
        $bobTask = Task::factory()->create(['board_id' => $board->id, 'board_column_id' => $column->id, 'department_id' => $board->department_id, 'created_by' => $hod->id, 'primary_assignee_id' => $bob->id]);
        $aliceTask->assignees()->attach($alice->id, ['assignment_type' => 'assignee']);
        $bobTask->assignees()->attach($bob->id, ['assignment_type' => 'assignee']);

        $taskIds = fn ($response) => collect($response->viewData('page')['props']['board']['columns'])->flatMap(fn ($c) => collect($c['tasks'])->pluck('id'))->all();

        $asAlice = $this->actingAs($alice)->get("/boards/{$board->id}");
        $this->assertSame([$aliceTask->id], $taskIds($asAlice));
        $asAlice->assertInertia(fn ($page) => $page->where('hodBoard', null)->where('scoreBoard.kind', 'cs'));

        $asHod = $this->actingAs($hod)->get("/boards/{$board->id}");
        $this->assertEqualsCanonicalizing([$aliceTask->id, $bobTask->id], $taskIds($asHod));
        $asHod->assertInertia(fn ($page) => $page->where('scoreBoard', null)->has('hodBoard.data.employees', 2));
    }

    public function test_other_departments_boards_are_untouched(): void
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $user = User::factory()->create(['department_id' => $marketing->id])->assignRole('Marketing');
        $board = Board::factory()->create(['department_id' => $marketing->id]);

        $this->actingAs($user)->get("/boards/{$board->id}")->assertInertia(fn ($page) => $page
            ->where('scoreBoard', null)
            ->where('hodBoard', null));
    }
}
