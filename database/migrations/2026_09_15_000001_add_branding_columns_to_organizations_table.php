<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'brand_name')) {
                $table->string('brand_name', 120)->nullable();
            }
            if (!Schema::hasColumn('organizations', 'brand_logo_path')) {
                $table->string('brand_logo_path', 500)->nullable();
            }
            if (!Schema::hasColumn('organizations', 'brand_primary_color')) {
                $table->string('brand_primary_color', 7)->nullable();
            }
            if (!Schema::hasColumn('organizations', 'brand_footer_text')) {
                $table->string('brand_footer_text', 255)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'brand_name',
                'brand_logo_path',
                'brand_primary_color',
                'brand_footer_text',
            ]);
        });
    }
};
