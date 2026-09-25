<?php

namespace Tests\Feature\Cs;

use App\Models\Board;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;

/**
 * Shared set-up for the Customer Service Board tests. Every user's
 * department is set on the USER (User::department_id, the Departments page),
 * never only on the Employee record, because that is the field the board's
 * access rules and provisioning read.
 */
trait CsFixtures
{
    protected function csDepartment(): Department
    {
        return Department::query()->where('slug', 'customer-service')->firstOrFail();
    }

    /** @return array{0: User, 1: Employee} */
    protected function csMember(array $userAttributes = []): array
    {
        $cs = $this->csDepartment();
        $user = User::factory()->create(['department_id' => $cs->id, ...$userAttributes])->assignRole('Customer Service');
        $employee = Employee::factory()->create(['user_id' => $user->id, 'department_id' => $cs->id]);

        return [$user, $employee];
    }

    /**
     * The department's manager (Department Manager role) and assistant
     * manager (only the plain Customer Service role, deliberately: leadership
     * must not depend on holding a manager role permission). Both belong to
     * the department and have employee records, yet are never scored.
     *
     * @return array{0: User, 1: User}
     */
    protected function csLeaders(): array
    {
        $cs = $this->csDepartment();

        $hod = User::factory()->create(['department_id' => $cs->id])->assignRole('Department Manager');
        $assistant = User::factory()->create(['department_id' => $cs->id])->assignRole('Customer Service');
        Employee::factory()->create(['user_id' => $hod->id, 'department_id' => $cs->id]);
        Employee::factory()->create(['user_id' => $assistant->id, 'department_id' => $cs->id]);

        $cs->update(['manager_id' => $hod->id, 'assistant_manager_id' => $assistant->id]);

        return [$hod, $assistant];
    }

    protected function csBoard(): Board
    {
        return Board::factory()->create([
            'department_id' => $this->csDepartment()->id,
            'name' => 'Customer Service',
            'visibility' => Board::VISIBILITY_DEPARTMENT,
        ]);
    }

    /** A user in another department who still holds the coarse cs.cards.* permissions through the role. */
    protected function marketingManagerWithCsPermissions(): User
    {
        $marketing = Department::query()->where('slug', 'marketing')->firstOrFail();
        $manager = User::factory()->create(['department_id' => $marketing->id])->assignRole('Department Manager');
        $marketing->update(['manager_id' => $manager->id]);

        return $manager;
    }
}
