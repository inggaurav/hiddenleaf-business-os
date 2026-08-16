<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->string('module')->default('general')->after('name');
            $table->boolean('is_enabled')->default(true)->after('variables');
            $table->index(['workspace_id', 'module']);
        });

        Schema::table('notification_templates', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true)->after('variables');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['workspace_id', 'module']);
        });

        Schema::table('webhooks', function (Blueprint $table) {
            $table->json('events')->nullable()->after('event');
        });
    }

    public function down(): void
    {
        Schema::table('webhooks', fn (Blueprint $table) => $table->dropColumn('events'));
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropForeign(['workspace_id']);
            $table->dropForeign(['created_by']);
            $table->dropIndex(['workspace_id', 'module']);
            $table->dropColumn(['workspace_id', 'is_enabled', 'created_by']);
        });
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'module']);
            $table->dropColumn(['module', 'is_enabled']);
        });
    }
};
