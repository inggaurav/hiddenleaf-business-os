<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Workspace;
use HiddenLeaf\Kernel\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OrganizationBrandingController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $orgId = (int) $request->session()->get('active_organization_id');
        $organization = Organization::findOrFail($orgId);

        $isOrgOwner = $user->isSuperAdmin() || (int) $organization->owner_id === (int) $user->id;
        abort_unless($isOrgOwner, 403, 'Only organization owners or platform administrators can manage branding.');

        return Inertia::render('Settings/Branding', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'brand_name' => $organization->brand_name,
                'brand_logo_path' => $organization->brand_logo_path,
                'brand_primary_color' => $organization->brand_primary_color,
                'brand_footer_text' => $organization->brand_footer_text,
            ],
            'isOrgOwner' => $isOrgOwner,
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger)
    {
        $user = $request->user();
        $orgId = (int) $request->session()->get('active_organization_id');
        $organization = Organization::findOrFail($orgId);

        $isOrgOwner = $user->isSuperAdmin() || (int) $organization->owner_id === (int) $user->id;
        abort_unless($isOrgOwner, 403, 'Only organization owners or platform administrators can update branding.');

        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'brand_logo' => ['nullable', 'file', 'image', 'max:2048'],
            'brand_primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'brand_footer_text' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($request, $organization, $data, $user, $auditLogger) {
            $updateData = [
                'brand_name' => $data['brand_name'] ?? null,
                'brand_primary_color' => $data['brand_primary_color'] ?? null,
                'brand_footer_text' => $data['brand_footer_text'] ?? null,
            ];

            if ($request->hasFile('brand_logo')) {
                $file = $request->file('brand_logo');
                $path = Storage::disk('public')->put("brands/{$organization->id}/logo", $file);
                $updateData['brand_logo_path'] = Storage::url($path);
            }

            $organization->update($updateData);

            $workspaceId = (int) $request->session()->get('active_workspace_id');
            $auditLogger->log(
                actorId: $user->id,
                orgId: $organization->id,
                workspaceId: $workspaceId > 0 ? $workspaceId : null,
                action: 'organization.branding_updated',
                entity: 'organization',
                entityId: (string) $organization->id,
                metadata: [
                    'brand_name' => $updateData['brand_name'],
                    'has_logo' => isset($updateData['brand_logo_path']),
                ],
                critical: false
            );
        });

        return back()->with('success', 'Branding updated.');
    }
}
