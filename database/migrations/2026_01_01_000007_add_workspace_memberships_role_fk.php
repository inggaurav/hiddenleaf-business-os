<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_memberships', function (Blueprint $table) {
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('set null');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['organization_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'name']);
        });

        Schema::table('workspace_memberships', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
    }
};
