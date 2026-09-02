<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\UpdatePerformanceReviewRequest;
use App\Models\PerformanceReview;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceReviewController extends Controller
{
    public function show(Request $request, PerformanceReview $review): Response
    {
        Gate::authorize('view', $review);

        $review->load('cycle:id,name,period_start,period_end', 'employee:id,first_name,middle_name,last_name,job_title', 'reviewer:id,name');
        $user = $request->user();

        return Inertia::render('hr/performance/show', [
            'review' => [
                'id' => $review->id,
                'employee' => $review->employee->full_name,
                'job_title' => $review->employee->job_title,
                'reviewer' => $review->reviewer?->name,
                'cycle' => $review->cycle->name,
                'status' => $review->status,
                'self_assessment' => $review->self_assessment ?? [],
                'manager_assessment' => $review->manager_assessment ?? [],
                'overall_rating' => $review->overall_rating !== null ? (float) $review->overall_rating : null,
                'submitted_at' => $review->submitted_at,
                'shared_at' => $review->shared_at,
                'acknowledged_at' => $review->acknowledged_at,
            ],
            'can' => [
                'selfAssess' => $user->can('selfAssess', $review),
                'managerAssess' => $user->can('managerAssess', $review),
                'submitSelf' => $user->can('submitSelf', $review),
                'share' => $user->can('share', $review),
                'acknowledge' => $user->can('acknowledge', $review),
            ],
        ]);
    }

    public function update(UpdatePerformanceReviewRequest $request, PerformanceReview $review): RedirectResponse
    {
        $data = $request->validated();
        $updates = [];

        if (array_key_exists('self_assessment', $data)) {
            Gate::authorize('selfAssess', $review);
            $updates['self_assessment'] = $data['self_assessment'];
        }

        if (array_key_exists('manager_assessment', $data) || array_key_exists('overall_rating', $data)) {
            Gate::authorize('managerAssess', $review);
            if (array_key_exists('manager_assessment', $data)) {
                $updates['manager_assessment'] = $data['manager_assessment'];
            }
            if (array_key_exists('overall_rating', $data)) {
                $updates['overall_rating'] = $data['overall_rating'];
            }
        }

        abort_if($updates === [], 422, 'Nothing to update.');
        $review->update($updates);

        return back()->with('success', 'Review saved.');
    }

    public function transition(Request $request, PerformanceReview $review): RedirectResponse
    {
        $to = $request->string('to')->toString();
        $user = $request->user();

        $ability = match ($to) {
            'submit_self' => 'submitSelf',
            'share' => 'share',
            'acknowledge' => 'acknowledge',
            default => throw ValidationException::withMessages(['to' => 'Unknown transition.']),
        };

        abort_unless($user->can($ability, $review), 403);

        $review->update(match ($to) {
            'submit_self' => ['status' => PerformanceReview::STATUS_MANAGER_REVIEW, 'submitted_at' => now()],
            'share' => ['status' => PerformanceReview::STATUS_SHARED, 'shared_at' => now()],
            'acknowledge' => ['status' => PerformanceReview::STATUS_ACKNOWLEDGED, 'acknowledged_at' => now()],
        });

        AuditLogger::log($review, "performance_review_{$to}", [], []);

        return back()->with('success', 'Review updated.');
    }
}
