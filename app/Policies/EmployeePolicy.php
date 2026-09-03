<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.employees.view');
    }

    /**
     * HR sees everyone. A manager sees their own reports (either by the
     * employee's manager chain or by managing the employee's department).
     * Anyone sees their own linked record.
     */
    public function view(User $user, Employee $employee): bool
    {
        if ($user->can('hr.employees.view') && ($user->can('hr.employees.manage') || $this->manages($user, $employee))) {
            return true;
        }

        if ($user->can('hr.employees.manage')) {
            return true;
        }

        return $employee->user_id === $user->id || $this->manages($user, $employee);
    }

    public function create(User $user): bool
    {
        return $user->can('hr.employees.manage');
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.manage');
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->can('hr.employees.manage');
    }

    /** Salary and bank details — a tighter gate than the rest of the record. */
    public function viewCompensation(User $user, Employee $employee): bool
    {
        return $user->can('hr.compensation.view');
    }

    public function manageCompensation(User $user, Employee $employee): bool
    {
        return $user->can('hr.compensation.manage');
    }

    /**
     * Filing a leave request on this employee's behalf: HR/CEO/Admin for
     * anyone; a line manager or department head only for their own people.
     * Never for yourself — that's the ordinary self-service path.
     */
    public function fileLeaveFor(User $user, Employee $employee): bool
    {
        if ($employee->user_id === $user->id) {
            return false;
        }

        return $user->can('hr.leave.manage')
            || $employee->manager?->user_id === $user->id
            || $this->manages($user, $employee);
    }

    private function manages(User $user, Employee $employee): bool
    {
        if ($employee->user_id !== null && $employee->user?->manager_id === $user->id) {
            return true;
        }

        if ($employee->department_id === null) {
            return false;
        }

        return Department::query()
            ->where('id', $employee->department_id)
            ->where(fn ($q) => $q->where('manager_id', $user->id)->orWhere('assistant_manager_id', $user->id))
            ->exists();
    }
}
