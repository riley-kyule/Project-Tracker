<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Models\SeoTaskTemplate;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The HOD-managed item library — SEO Board Requirements Specification v1.1
 * §4.4/§5.1, §9 workflow rule 1. Viewed on the "SEO Board Settings" hub
 * (SeoSettingsController); this controller is action-only.
 */
class SeoTaskTemplateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', SeoTaskTemplate::class);

        $validated = $this->validated($request);

        $template = SeoTaskTemplate::query()->create([...$validated, 'created_by' => $request->user()->id]);

        AuditLogger::log($template, 'seo_template.created', [], $template->only(array_keys($validated)));

        return back()->with('success', 'Template created.');
    }

    public function update(Request $request, SeoTaskTemplate $template): RedirectResponse
    {
        Gate::authorize('update', $template);

        $validated = $this->validated($request);
        $old = $template->only(array_keys($validated));
        $template->update($validated);

        AuditLogger::log($template, 'seo_template.updated', $old, $template->only(array_keys($validated)));

        return back()->with('success', 'Template updated.');
    }

    public function destroy(SeoTaskTemplate $template): RedirectResponse
    {
        Gate::authorize('delete', $template);

        $old = $template->only(['id', 'name']);
        $template->update(['is_active' => false]);

        AuditLogger::log($template, 'seo_template.deactivated', $old, ['is_active' => false]);

        return back()->with('success', 'Template deactivated.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'card_type' => ['required', 'string', 'in:'.SeoTaskTemplate::CARD_TYPE_DAILY.','.SeoTaskTemplate::CARD_TYPE_WEEKLY],
            'section' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'classification' => ['required', 'string', 'in:mandatory,production,scheduled,conditional,additional'],
            'default_weight' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'min_weight' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'max_weight' => ['required', 'numeric', 'min:0.5', 'max:100', 'gte:min_weight'],
            'requires_quantity' => ['boolean'],
            'quantity_unit' => ['nullable', 'string', 'max:50'],
            'evidence_type' => ['nullable', 'string', 'max:50'],
            'evidence_required' => ['boolean'],
            'completion_criteria' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'position' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
