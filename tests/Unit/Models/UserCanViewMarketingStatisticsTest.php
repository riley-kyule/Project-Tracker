<?php

namespace Tests\Unit\Models;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserCanViewMarketingStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_direct_marketing_department_member_can_view()
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $user = User::factory()->create(['department_id' => $marketing->id])->assignRole('Employee');

        $this->assertTrue($user->canViewMarketingStatistics());
    }

    public function test_a_marketing_sub_department_member_can_view()
    {
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $user = User::factory()->create(['department_id' => $seo->id])->assignRole('Employee');

        $this->assertTrue($user->canViewMarketingStatistics());
    }

    public function test_a_member_of_an_unrelated_department_cannot_view()
    {
        $it = Department::query()->where('slug', 'it')->firstOrFail();
        $user = User::factory()->create(['department_id' => $it->id])->assignRole('Employee');

        $this->assertFalse($user->canViewMarketingStatistics());
    }

    public function test_a_user_with_no_department_but_the_explicit_permission_can_view()
    {
        $user = User::factory()->create(['department_id' => null])->assignRole('Marketing');

        $this->assertTrue($user->canViewMarketingStatistics());
    }

    public function test_the_query_scope_matches_the_instance_method()
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $seo = Department::query()->where('slug', 'seo')->firstOrFail();
        $it = Department::query()->where('slug', 'it')->firstOrFail();

        $marketingMember = User::factory()->create(['department_id' => $marketing->id])->assignRole('Employee');
        $seoMember = User::factory()->create(['department_id' => $seo->id])->assignRole('Employee');
        $permissionHolder = User::factory()->create(['department_id' => null])->assignRole('Marketing');
        $itMember = User::factory()->create(['department_id' => $it->id])->assignRole('Employee');

        $ids = User::query()->canViewMarketingStatistics()->pluck('id')->all();

        $this->assertContains($marketingMember->id, $ids);
        $this->assertContains($seoMember->id, $ids);
        $this->assertContains($permissionHolder->id, $ids);
        $this->assertNotContains($itMember->id, $ids);
    }
}
