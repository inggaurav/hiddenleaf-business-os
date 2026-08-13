<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_directories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('media_directories')->nullOnDelete();
            $table->index(['workspace_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('media_directories', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'parent_id']);
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
