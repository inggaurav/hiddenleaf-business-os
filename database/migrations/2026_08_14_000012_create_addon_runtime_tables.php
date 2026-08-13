<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $t) {
            $t->id();
            $t->string('addon_id')->unique();
            $t->string('alias')->unique();
            $t->string('name');
            $t->string('version');
            $t->string('minimum_core');
            $t->json('dependencies');
            $t->json('manifest');
            $t->string('status')->default('installed');
            $t->timestamps();
        });
        Schema::create('workspace_addons', function (Blueprint $t) {
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->foreignId('addon_id')->constrained('addons')->cascadeOnDelete();
            $t->json('configuration')->nullable();
            $t->boolean('is_active')->default(false);
            $t->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->primary(['workspace_id', 'addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_addons');
        Schema::dropIfExists('addons');
    }
};
