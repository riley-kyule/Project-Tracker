<?php

namespace App\Policies;

use App\Models\PerformanceReview;
use App\Models\User;

class PerformanceReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hr.performance.view') || $user->employee()->exists();
    }

    public function view(User $user, PerformanceReview $review): bool
    {
        return $user->can('hr.performance.view')
            || $review->employee->user_id === $user->id
            || $review->reviewer_id === $user->id;
    }

    /** The subject employee editing their self-assessment while it's their turn. */
    public function selfAssess(User $user, PerformanceReview $review): bool
    {
        return $review->employee->user_id === $user->id
            && in_array($review->status, [PerformanceReview::STATUS_SELF_REVIEW, PerformanceReview::STATUS_MANAGER_REVIEW], true);
    }

    /** The reviewer (or HR) editing the manager assessment. */
    public function managerAssess(User $user, PerformanceReview $review): bool
    {
        return ($review->reviewer_id === $user->id || $user->can('hr.performance.manage'))
            && in_array($review->status, [PerformanceReview::STATUS_MANAGER_REVIEW, PerformanceReview::STATUS_SELF_REVIEW], true);
    }

    public function share(User $user, PerformanceReview $review): bool
    {
        return ($review->reviewer_id === $user->id || $user->can('hr.performance.manage'))
            && $review->status === PerformanceReview::STATUS_MANAGER_REVIEW;
    }

    public function acknowledge(User $user, PerformanceReview $review): bool
    {
        return $review->employee->user_id === $user->id && $review->status === PerformanceReview::STATUS_SHARED;
    }

    /** The subject employee submitting their self-assessment to the reviewer. */
    public function submitSelf(User $user, PerformanceReview $review): bool
    {
        return $review->employee->user_id === $user->id && $review->status === PerformanceReview::STATUS_SELF_REVIEW;
    }
}
