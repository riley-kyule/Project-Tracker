<?php

namespace Tests\Feature\Admin;

use App\Models\Label;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_a_label()
    {
        $admin = User::factory()->create()->assignRole('Administrator');

        $this->actingAs($admin)
            ->post('/admin/labels', ['name' => 'Urgent', 'color' => '#ff0000'])
            ->assertRedirect();

        $this->assertDatabaseHas('labels', ['name' => 'Urgent', 'color' => '#ff0000']);
    }

    public function test_non_manager_cannot_create_a_label()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)
            ->post('/admin/labels', ['name' => 'Urgent', 'color' => '#ff0000'])
            ->assertForbidden();
    }

    public function test_non_manager_cannot_view_the_labels_index()
    {
        $employee = User::factory()->create()->assignRole('Employee');

        $this->actingAs($employee)->get('/admin/labels')->assertForbidden();
    }

    public function test_administrator_can_rename_a_label()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $label = Label::factory()->create(['name' => 'Old name']);

        $this->actingAs($admin)
            ->patch("/admin/labels/{$label->id}", ['name' => 'New name', 'color' => $label->color])
            ->assertRedirect();

        $this->assertDatabaseHas('labels', ['id' => $label->id, 'name' => 'New name']);
    }

    public function test_deleting_an_unused_label_succeeds()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $label = Label::factory()->create();

        $this->actingAs($admin)->delete("/admin/labels/{$label->id}")->assertRedirect();

        $this->assertDatabaseMissing('labels', ['id' => $label->id]);
    }

    public function test_deleting_a_label_still_attached_to_a_task_is_blocked()
    {
        $admin = User::factory()->create()->assignRole('Administrator');
        $label = Label::factory()->create();
        $task = Task::factory()->create();
        $task->labels()->attach($label);

        $this->actingAs($admin)->delete("/admin/labels/{$label->id}")->assertSessionHasErrors('label');

        $this->assertDatabaseHas('labels', ['id' => $label->id]);
    }
}
