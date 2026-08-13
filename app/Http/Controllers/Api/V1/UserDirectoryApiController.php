<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserDirectoryApiController extends Controller
{
    public function users(Request $request)
    {
        if ($request->route('type')) {
            $request->merge(['type' => $request->route('type')]);
        }
        $validated = $request->validate([
            'type' => ['required', Rule::in(['staff', 'client', 'vendor'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $workspace = $request->attributes->get('workspace');
        $roles = match ($validated['type']) {
            'staff' => ['user', 'staff', 'company_admin'],
            'client' => ['client'],
            'vendor' => ['vendor'],
        };
        $users = User::query()
            ->whereHas('workspaces', fn ($query) => $query->where('workspaces.id', $workspace->id))
            ->whereIn('role', $roles)
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where(function ($nested) use ($search) {
                $nested->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return UserResource::collection($users)->additional(['type' => $validated['type']]);
    }

    public function subscription(Request $request)
    {
        $workspace = $request->attributes->get('workspace');
        $subscription = Subscription::query()
            ->with('plan')
            ->where('organization_id', $workspace->organization_id)
            ->latest('id')
            ->first();

        return response()->json([
            'data' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at,
                'expires_at' => $subscription->expires_at,
                'is_active' => $subscription->isActive(),
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'modules' => $subscription->plan->modules,
                    'workspace_limit' => $subscription->plan->workspace_limit,
                    'number_of_users' => $subscription->plan->number_of_users,
                    'storage_limit' => $subscription->plan->storage_limit,
                ] : null,
            ] : null,
        ]);
    }
}
