<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Addon;
use App\Models\WorkspaceAddon;
use App\Models\UserActiveModule;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $addons = DB::table('addons')->get();
        foreach ($addons as $addon) {
            $alias = strtolower($addon->alias);
            $activeModules = DB::table('user_active_modules')
                ->whereRaw('LOWER(module_name) = ?', [$alias])
                ->get();
                
            foreach ($activeModules as $module) {
                DB::table('workspace_addons')->updateOrInsert(
                    ['workspace_id' => $module->workspace_id, 'addon_id' => $addon->id],
                    ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration required for backfilling data
    }
};
