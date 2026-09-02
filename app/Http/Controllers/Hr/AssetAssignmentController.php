<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\AssignAssetRequest;
use App\Http\Requests\Hr\ReturnAssetRequest;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Services\Hr\AssetRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AssetAssignmentController extends Controller
{
    public function __construct(private readonly AssetRegistry $registry) {}

    public function store(AssignAssetRequest $request, Asset $asset): RedirectResponse
    {
        Gate::authorize('update', $asset);

        $employee = Employee::findOrFail($request->integer('employee_id'));

        $this->registry->assign($asset, $employee, $request->user(), $request->safe()->except('employee_id'));

        return back()->with('success', "Assigned to {$employee->full_name}.");
    }

    public function update(ReturnAssetRequest $request, Asset $asset, AssetAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $asset);
        abort_unless($assignment->asset_id === $asset->id, 404);

        $this->registry->receiveReturn($assignment, $request->user(), $request->validated());

        return back()->with('success', 'Return recorded.');
    }
}
