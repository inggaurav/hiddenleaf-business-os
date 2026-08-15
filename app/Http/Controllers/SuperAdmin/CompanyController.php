<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->get('search', ''));

        $companies = Organization::query()
            ->with(['owner:id,name,email', 'plan:id,name'])
            ->withCount(['workspaces', 'members'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhereHas('owner', fn ($owner) => $owner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('SuperAdmin/Companies/Index', [
            'companies' => $companies,
            'plans' => Plan::where('status', true)->orderBy('name')->get(['id', 'name']),
            'filters' => ['search' => $search],
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $data = $request->validate([
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'is_active' => ['required', 'boolean'],
        ]);

        $organization->update([
            'plan_id' => $data['plan_id'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        return back()->with('success', 'Company access and plan updated.');
    }
}
