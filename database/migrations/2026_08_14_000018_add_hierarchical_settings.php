<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('scope')->default('platform')->after('key');
            $table->unsignedBigInteger('scope_id')->default(0)->after('scope');
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('is_encrypted')->default(false);
        });

        DB::table('settings')->whereNotNull('workspace_id')->orderBy('id')->each(function ($setting): void {
            $workspace = DB::table('workspaces')->find($setting->workspace_id);
            DB::table('settings')->where('id', $setting->id)->update([
                'scope' => 'workspace',
                'scope_id' => $setting->workspace_id,
                'organization_id' => $workspace?->organization_id,
            ]);
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->unique(['scope', 'scope_id', 'key']);
            $table->index(['organization_id', 'workspace_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropUnique(['scope', 'scope_id', 'key']);
            $table->dropIndex(['organization_id', 'workspace_id', 'user_id']);
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['scope', 'scope_id', 'is_encrypted']);
        });
    }
};
