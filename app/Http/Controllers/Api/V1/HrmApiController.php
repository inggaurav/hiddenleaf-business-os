<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HrEmployee;
use Illuminate\Http\Request;

class HrmApiController extends Controller
{
    public function employees(Request $request)
    {
        $workspace = $request->attributes->get('workspace');
        abort_unless($request->user()->isSuperAdmin() || $request->user()->canInWorkspace('hrm.view', $workspace), 403);

        return response()->json(HrEmployee::forWorkspace($workspace->organization_id, $workspace->id)
            ->orderBy('name')->paginate(min((int) $request->input('per_page', 20), 100)));
    }
}
