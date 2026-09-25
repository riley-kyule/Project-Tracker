<?php

namespace App\Services\Cs;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The one place that decides who may see and manage Customer Service Board
 * records, so every policy, controller, query and MCP tool agrees, and so the
 * Score Based tab can never disagree with the Kanban tab about who is
 * "leadership": the same Department::isLedBy() (manager and assistant
 * manager) and CEO/Administrator roles the rest of EWMS already uses.
 *
 * Ownership is the employee record's user account, and the department that
 * counts is always User::department_id (the Departments page), never the HR
 * record's Employee::department_id.
 */
class CsAccess
{
    public const DEPARTMENT_SLUG = 'customer-service';

    public static function department(): ?Department
    {
        return Department::query()->where('slug', self::DEPARTMENT_SLUG)->first();
    }

    /** CEO, Administrator, or the manager/assistant manager of $department. */
    public static function leads(User $user, ?Department $department): bool
    {
        if ($user->hasAnyRole(['CEO', 'Administrator'])) {
            return true;
        }

        return $department !== null && $department->isLedBy($user);
    }

    /** Leadership over the Customer Service department as a whole. */
    public static function leadsBoard(User $user): bool
    {
        return self::leads($user, self::department());
    }

    /**
     * Leadership over a scored Customer Service employee: the employee must
     * currently be a CS team member (so a manager elsewhere can never attach
     * CS records to their own staff) and $user must lead their department.
     */
    public static function leadsEmployee(User $user, Employee $employee): bool
    {
        $employeeUser = $employee->user;

        return $employeeUser !== null
            && $employeeUser->isCsEmployee()
            && self::leads($user, $employeeUser->department);
    }

    /**
     * Every active, scored Customer Service team member (a CS department or
     * sub-department user account that is active and doesn't lead it), with
     * the user relation loaded. The single roster used by provisioning, the
     * HOD team view and the trends table, so they can never disagree.
     *
     * @return Collection<int, Employee>
     */
    public static function scoredEmployees(?Department $department = null): Collection
    {
        $department ??= self::department();
        if ($department === null) {
            return new Collection;
        }

        return Employee::query()->active()
            ->whereHas('user', fn ($q) => $q->whereIn('department_id', $department->descendantIds())->where('status', User::STATUS_ACTIVE))
            ->with('user')
            ->orderBy('first_name')
            ->get()
            ->filter(fn (Employee $employee) => $employee->user->isCsEmployee())
            ->values();
    }

    public static function owns(User $user, Employee $employee): bool
    {
        return $employee->user_id !== null && $employee->user_id === $user->id;
    }
}
