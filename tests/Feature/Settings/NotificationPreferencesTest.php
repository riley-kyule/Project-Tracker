<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_preferences_page_defaults_everything_to_enabled()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/notifications');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('preferences.task_assigned', true));
    }

    public function test_preferences_can_be_updated()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/notifications', ['preferences' => ['task_assigned' => false]])
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->wantsNotification('task_assigned'));
        $this->assertTrue($user->wantsNotification('task_commented'));
    }
}
