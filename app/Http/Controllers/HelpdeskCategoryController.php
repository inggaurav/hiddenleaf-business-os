<?php

namespace App\Http\Controllers;

use App\Models\HelpdeskCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HelpdeskCategoryController extends Controller
{
    public function index(Request $request)
    {
        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        $categories = HelpdeskCategory::query()
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($orgId, fn ($q) => $q->where('organization_id', $orgId))
            ->withCount('tickets')
            ->latest()
            ->paginate($request->input('per_page', 10))
            ->withQueryString();

        return Inertia::render('Helpdesk/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create()
    {
        return Inertia::render('Helpdesk/Categories/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:30',
        ]);

        $wsId = session('active_workspace_id');
        $orgId = session('active_organization_id');

        HelpdeskCategory::create([
            'name' => $validated['name'],
            'color' => $validated['color'] ?? '#6366f1',
            'organization_id' => $orgId,
            'workspace_id' => $wsId,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('helpdesk-categories.index')->with('success', 'Helpdesk category created.');
    }

    public function show(HelpdeskCategory $helpdeskCategory)
    {
        return redirect()->route('helpdesk-categories.edit', $helpdeskCategory);
    }

    public function edit(HelpdeskCategory $helpdeskCategory)
    {
        return Inertia::render('Helpdesk/Categories/Edit', [
            'category' => $helpdeskCategory,
        ]);
    }

    public function update(Request $request, HelpdeskCategory $helpdeskCategory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'color' => 'nullable|string|max:30',
        ]);

        $helpdeskCategory->update($validated);

        return redirect()->route('helpdesk-categories.index')->with('success', 'Category updated.');
    }

    public function destroy(HelpdeskCategory $helpdeskCategory)
    {
        $helpdeskCategory->delete();

        return redirect()->route('helpdesk-categories.index')->with('success', 'Category deleted.');
    }
}
