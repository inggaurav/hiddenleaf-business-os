<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_deal_files')) {
            Schema::table('crm_deal_files', function (Blueprint $table) {
                if (! Schema::hasColumn('crm_deal_files', 'lead_id')) {
                    $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crm_deal_files')) {
            Schema::table('crm_deal_files', function (Blueprint $table) {
                if (Schema::hasColumn('crm_deal_files', 'lead_id')) {
                    if (DB::getDriverName() !== 'sqlite') {
                        $table->dropForeign(['lead_id']);
                        $table->dropColumn('lead_id');
                    }
                }
            });
        }
    }
};
