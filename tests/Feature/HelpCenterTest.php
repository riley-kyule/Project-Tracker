<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/help')->assertRedirect('/login');
    }

    public function test_any_signed_in_user_can_open_the_help_center(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/help')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('help/index')->has('auth.permissions')->has('auth.roles'));
    }
}
