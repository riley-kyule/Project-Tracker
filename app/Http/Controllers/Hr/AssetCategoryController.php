<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StoreAssetCategoryRequest;
use App\Models\Asset;
use App\Models\AssetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class AssetCategoryController extends Controller
{
    public function store(StoreAssetCategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Asset::class);

        AssetCategory::create($request->validated());

        return back()->with('success', 'Category added.');
    }

    public function update(StoreAssetCategoryRequest $request, AssetCategory $category): RedirectResponse
    {
        Gate::authorize('create', Asset::class);

        $category->update($request->validated());

        return back()->with('success', 'Category updated.');
    }
}
