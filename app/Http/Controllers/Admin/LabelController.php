<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LabelRequest;
use App\Models\Label;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LabelController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Label::class);

        return Inertia::render('admin/labels/index', [
            'labels' => Label::query()->withCount('tasks')->orderBy('name')->get(),
            'canManage' => Gate::allows('create', Label::class),
        ]);
    }

    public function store(LabelRequest $request): RedirectResponse
    {
        Gate::authorize('create', Label::class);

        $label = Label::create($request->validated());
        AuditLogger::log($label, 'created', [], $label->only(['name', 'color']));

        return back()->with('success', 'Label created.');
    }

    public function update(LabelRequest $request, Label $label): RedirectResponse
    {
        Gate::authorize('update', $label);

        $old = $label->only(['name', 'color']);
        $label->update($request->validated());
        AuditLogger::log($label, 'updated', $old, $label->only(['name', 'color']));

        return back()->with('success', 'Label updated.');
    }

    public function destroy(Label $label): RedirectResponse
    {
        Gate::authorize('delete', $label);

        if ($label->tasks()->exists()) {
            throw ValidationException::withMessages([
                'label' => 'Remove this label from every task before deleting it.',
            ]);
        }

        AuditLogger::log($label, 'deleted', $label->only(['name', 'color']), []);
        $label->delete();

        return back()->with('success', 'Label deleted.');
    }
}
