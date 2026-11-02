<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ComplaintCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComplaintCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ComplaintCategory::query()->orderBy('name')->get();

        return view('admin.complaint-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.complaint-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:complaint_categories,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        ComplaintCategory::create($validated);

        return redirect()->route('admin.complaint-categories.index')
            ->with('status', 'Complaint category created successfully.');
    }

    public function edit(ComplaintCategory $complaint_category): View
    {
        return view('admin.complaint-categories.edit', ['category' => $complaint_category]);
    }

    public function update(Request $request, ComplaintCategory $complaint_category): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('complaint_categories', 'code')->ignore($complaint_category->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $complaint_category->update($validated);

        return redirect()->route('admin.complaint-categories.index')
            ->with('status', 'Complaint category updated successfully.');
    }

    public function destroy(ComplaintCategory $complaint_category): RedirectResponse
    {
        $complaint_category->delete();

        return redirect()->route('admin.complaint-categories.index')
            ->with('status', 'Complaint category deleted successfully.');
    }
}
