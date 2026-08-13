<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Nwidart\Modules\Facades\Module;
use App\Models\UserActiveModule;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;

class ModuleController
{
    public function index(Request $request)
    {
        $workspaceId = $request->session()->get('active_workspace_id');
        if (!$workspaceId) abort(403, 'No active workspace');
        
        $modules = Module::all();
        $activeModules = UserActiveModule::where('workspace_id', $workspaceId)->pluck('module_name')->toArray();

        $formattedModules = [];
        foreach ($modules as $module) {
            $formattedModules[] = [
                'name' => $module->getName(),
                'description' => $module->getDescription(),
                'active' => in_array($module->getName(), $activeModules),
            ];
        }

        return Inertia::render('Modules/Index', [
            'modules' => $formattedModules
        ]);
    }

    public function toggle(Request $request)
    {
        $request->validate([
            'module_name' => 'required|string',
            'active' => 'required|boolean'
        ]);

        $workspaceId = $request->session()->get('active_workspace_id');
        if (!$workspaceId) abort(403, 'No active workspace');

        if ($request->boolean('active')) {
            UserActiveModule::updateOrCreate([
                'workspace_id' => $workspaceId,
                'module_name' => $request->module_name
            ], []);
        } else {
            UserActiveModule::where('workspace_id', $workspaceId)
                ->where('module_name', $request->module_name)
                ->delete();
        }

        return redirect()->back()->with('success', 'Module status updated.');
    }
}
