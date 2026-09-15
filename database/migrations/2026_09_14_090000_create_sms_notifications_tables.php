<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('driver'); // msg91, twilio, fast2sms, fake
            $table->string('name');
            $table->text('credentials'); // Encrypted JSON
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'is_default']);
            $table->index(['workspace_id', 'is_active']);
        });

        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['workspace_id', 'is_active']);
        });

        Schema::create('sms_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('sms_templates')->cascadeOnDelete();
            $table->string('event_name');
            $table->json('conditions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_fired_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'event_name', 'is_active']);
        });

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('to_number');
            $table->string('to_name')->nullable();
            $table->text('body_sent');
            $table->string('gateway_driver');
            $table->string('status')->default('sent'); // sent, failed, queued
            $table->text('error_message')->nullable();
            $table->string('triggered_by')->default('manual'); // manual, automation, event, mrfox
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->integer('cost_units')->default(1);
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
        Schema::dropIfExists('sms_triggers');
        Schema::dropIfExists('sms_templates');
        Schema::dropIfExists('sms_gateways');
    }
};
