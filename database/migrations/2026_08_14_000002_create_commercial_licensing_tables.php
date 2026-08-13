<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('license_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('alias')->unique();
            $table->timestamps();
        });
        Schema::create('license_editions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('license_products')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
            $table->unique(['product_id', 'slug']);
        });
        Schema::create('license_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('license_customers');
            $table->foreignId('product_id')->constrained('license_products');
            $table->foreignId('edition_id')->nullable()->constrained('license_editions')->nullOnDelete();
            $table->foreignId('license_type_id')->nullable()->constrained('license_types')->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->unsignedInteger('activation_limit')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('grace_period_days')->default(14);
            $table->timestamps();
        });
        Schema::create('license_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->string('key_hash', 64)->unique();
            $table->string('key_prefix');
            $table->string('status')->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('license_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->string('domain');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['license_id', 'domain']);
        });
        Schema::create('license_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->uuid('installation_uuid');
            $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['license_id', 'installation_uuid']);
        });
        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->foreignId('license_key_id')->constrained('license_keys')->cascadeOnDelete();
            $table->foreignId('installation_id')->constrained('license_installations')->cascadeOnDelete();
            $table->string('domain');
            $table->string('status')->default('active')->index();
            $table->timestamp('activated_at');
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->unique(['license_key_id', 'installation_id', 'domain'], 'license_activation_identity');
        });
        Schema::create('license_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->string('key');
            $table->json('value');
            $table->timestamps();
            $table->unique(['license_id', 'key']);
        });
        Schema::create('licensing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->nullable()->constrained('licenses')->nullOnDelete();
            $table->foreignId('license_key_id')->nullable()->constrained('license_keys')->nullOnDelete();
            $table->foreignId('activation_id')->nullable()->constrained('license_activations')->nullOnDelete();
            $table->string('event');
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['licensing_events', 'license_entitlements', 'license_activations', 'license_installations', 'license_domains', 'license_keys', 'licenses', 'license_types', 'license_editions', 'license_products', 'license_customers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
