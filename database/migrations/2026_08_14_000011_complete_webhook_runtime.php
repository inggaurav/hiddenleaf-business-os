<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhooks', function (Blueprint $t) {
            $t->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $t->text('secret')->nullable()->after('method');
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('timeout_seconds')->default(10);
            $t->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
        Schema::create('webhook_deliveries', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $t->string('event');
            $t->string('idempotency_key')->unique();
            $t->json('payload');
            $t->unsignedInteger('attempts')->default(0);
            $t->string('status')->default('pending');
            $t->unsignedSmallInteger('response_status')->nullable();
            $t->text('response_body')->nullable();
            $t->text('error')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('next_attempt_at')->nullable();
            $t->timestamps();
            $t->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::table('webhooks', function (Blueprint $t) {
            $t->dropForeign(['organization_id']);
            $t->dropForeign(['workspace_id']);
            $t->dropColumn(['organization_id', 'secret', 'is_active', 'timeout_seconds']);
        });
    }
};
