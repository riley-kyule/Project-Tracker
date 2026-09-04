<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreAssetRequest;
use App\Http\Requests\Hr\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Services\AuditLogger;
use App\Services\Hr\AssetImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AssetController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Asset::class);

        $assets = Asset::query()
            ->with(['category:id,name', 'currentAssignment.employee:id,first_name,middle_name,last_name'])
            ->orderBy('name')
            ->limit(self::LIST_CAP)
            ->get();

        return Inertia::render('hr/assets/index', [
            'listCapped' => $assets->count() >= self::LIST_CAP,
            'assets' => $assets
                ->map(fn (Asset $a) => [
                    'id' => $a->id,
                    'asset_tag' => $a->asset_tag,
                    'name' => $a->name,
                    'category' => $a->category?->only(['id', 'name']),
                    'serial_number' => $a->serial_number,
                    'status' => $a->status,
                    'condition' => $a->condition,
                    'custodian' => $a->currentAssignment?->employee
                        ? ['id' => $a->currentAssignment->employee->id, 'name' => $a->currentAssignment->employee->full_name]
                        : null,
                ]),
            'categories' => AssetCategory::query()->orderBy('name')->get(['id', 'name', 'is_active']),
            'canManage' => request()->user()->can('hr.assets.manage'),
        ]);
    }

    public function show(Asset $asset): Response
    {
        Gate::authorize('view', $asset);

        $asset->load([
            'category:id,name',
            'assignments.employee:id,first_name,middle_name,last_name',
            'assignments.assignedBy:id,name',
        ]);

        return Inertia::render('hr/assets/show', [
            'asset' => [
                ...$asset->only([
                    'id', 'asset_tag', 'asset_category_id', 'name', 'description', 'serial_number',
                    'manufacturer', 'model', 'purchase_cost', 'supplier',
                    'status', 'condition', 'location', 'notes',
                ]),
                'purchase_date' => $asset->purchase_date?->toDateString(),
                'warranty_expiry' => $asset->warranty_expiry?->toDateString(),
                'category' => $asset->category?->only(['id', 'name']),
                'current_assignment_id' => $asset->currentAssignment?->id,
                'assignments' => $asset->assignments->map(fn ($a) => [
                    'id' => $a->id,
                    'employee' => $a->employee ? ['id' => $a->employee->id, 'name' => $a->employee->full_name] : null,
                    'assigned_by' => $a->assignedBy?->name,
                    'assigned_at' => $a->assigned_at,
                    'expected_return_at' => $a->expected_return_at?->toDateString(),
                    'returned_at' => $a->returned_at,
                    'condition_out' => $a->condition_out,
                    'condition_in' => $a->condition_in,
                    'notes' => $a->notes,
                ]),
            ],
            'categories' => AssetCategory::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->active()->orderBy('first_name')
                ->get(['id', 'first_name', 'middle_name', 'last_name'])
                ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->full_name]),
            'canManage' => request()->user()->can('hr.assets.manage'),
        ]);
    }

    public function import(Request $request, AssetImporter $importer): RedirectResponse
    {
        Gate::authorize('create', Asset::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $result = $importer->importFile($request->file('file')->getRealPath());

        if ($result->fatalError) {
            return back()->with('error', $result->fatalError);
        }

        $problems = [];
        if ($result->hasUnmatched()) {
            $problems[] = "No staff match these custodians yet: {$result->unmatchedList()} — import those employees, then upload again to link them.";
        }
        if ($result->warnings !== []) {
            $problems[] = implode(' ', array_slice($result->warnings, 0, 5))
                .(count($result->warnings) > 5 ? ' …and '.(count($result->warnings) - 5).' more.' : '');
        }

        return back()
            ->with('success', $result->summary())
            ->with('error', $problems === [] ? null : implode(' ', $problems));
    }

    public function store(StoreAssetRequest $request): RedirectResponse
    {
        Gate::authorize('create', Asset::class);

        $asset = Asset::create($request->validated());
        AuditLogger::log($asset, 'created', [], $asset->only(['asset_tag', 'name']));

        return redirect()->route('hr.assets.show', $asset)->with('success', 'Asset added.');
    }

    public function update(UpdateAssetRequest $request, Asset $asset): RedirectResponse
    {
        Gate::authorize('update', $asset);

        $old = $asset->only(array_keys($request->validated()));
        $asset->update($request->validated());
        AuditLogger::log($asset, 'updated', $old, $asset->only(array_keys($request->validated())));

        return back()->with('success', 'Asset updated.');
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);

        AuditLogger::log($asset, 'deleted', $asset->only(['asset_tag', 'name']), []);
        $asset->delete();

        return redirect()->route('hr.assets.index')->with('success', 'Asset removed.');
    }
}
