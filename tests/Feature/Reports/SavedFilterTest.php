<?php

namespace Tests\Feature\Reports;

use App\Models\Board;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_can_save_and_reuse_a_filter()
    {
        $manager = User::factory()->create()->assignRole('Department Manager');

        $this->actingAs($manager)
            ->post('/saved-filters', [
                'scope' => SavedFilter::SCOPE_REPORTS_TASKS,
                'name' => 'My overdue work',
                'filters' => ['filter' => 'overdue', 'assignee_id' => (string) $manager->id],
            ])
            ->assertRedirect();

        $saved = SavedFilter::query()->where('user_id', $manager->id)->firstOrFail();
        $this->assertSame('My overdue work', $saved->name);
        $this->assertSame('overdue', $saved->filters['filter']);

        $response = $this->actingAs($manager)->get('/reports/tasks')->assertOk();
        $names = collect($response->viewData('page')['props']['savedFilters'])->pluck('name');
        $this->assertTrue($names->contains('My overdue work'));
    }

    public function test_saving_a_filter_with_the_same_name_updates_it()
    {
        $manager = User::factory()->create()->assignRole('Department Manager');

        $this->actingAs($manager)->post('/saved-filters', [
            'scope' => SavedFilter::SCOPE_REPORTS_TASKS,
            'name' => 'Team view',
            'filters' => ['filter' => 'all'],
        ]);

        $this->actingAs($manager)->post('/saved-filters', [
            'scope' => SavedFilter::SCOPE_REPORTS_TASKS,
            'name' => 'Team view',
            'filters' => ['filter' => 'blocked'],
        ]);

        $this->assertSame(1, SavedFilter::query()->where('user_id', $manager->id)->count());
        $this->assertSame('blocked', SavedFilter::query()->where('user_id', $manager->id)->firstOrFail()->filters['filter']);
    }

    public function test_a_user_cannot_delete_someone_elses_saved_filter()
    {
        $owner = User::factory()->create()->assignRole('Department Manager');
        $other = User::factory()->create()->assignRole('Department Manager');

        $filter = SavedFilter::query()->create([
            'user_id' => $owner->id,
            'scope' => SavedFilter::SCOPE_REPORTS_TASKS,
            'name' => 'Mine',
            'filters' => ['filter' => 'all'],
        ]);

        $this->actingAs($other)->delete("/saved-filters/{$filter->id}")->assertForbidden();
        $this->actingAs($owner)->delete("/saved-filters/{$filter->id}")->assertRedirect();
        $this->assertModelMissing($filter);
    }

    public function test_a_user_can_save_and_reuse_a_board_scoped_filter()
    {
        $user = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_COMPANY]);

        $this->actingAs($user)
            ->post('/saved-filters', [
                'scope' => SavedFilter::boardScope($board->id),
                'name' => 'My critical work',
                'filters' => ['priority' => 'critical'],
            ])
            ->assertRedirect();

        $response = $this->actingAs($user)->get("/boards/{$board->id}")->assertOk();
        $names = collect($response->viewData('page')['props']['savedFilters'])->pluck('name');
        $this->assertTrue($names->contains('My critical work'));
    }

    public function test_a_user_cannot_save_a_filter_scoped_to_a_board_they_cannot_view()
    {
        $outsider = User::factory()->create()->assignRole('Employee');
        $board = Board::factory()->create(['visibility' => Board::VISIBILITY_RESTRICTED]);

        $this->actingAs($outsider)
            ->post('/saved-filters', [
                'scope' => SavedFilter::boardScope($board->id),
                'name' => 'Sneaky',
                'filters' => ['priority' => 'critical'],
            ])
            ->assertSessionHasErrors('scope');

        $this->assertDatabaseMissing('saved_filters', ['name' => 'Sneaky']);
    }

    public function test_an_unrecognized_scope_is_rejected()
    {
        $user = User::factory()->create()->assignRole('Employee');

        $this->actingAs($user)
            ->post('/saved-filters', [
                'scope' => 'not.a.real.scope',
                'name' => 'Nope',
                'filters' => [],
            ])
            ->assertSessionHasErrors('scope');
    }
}
