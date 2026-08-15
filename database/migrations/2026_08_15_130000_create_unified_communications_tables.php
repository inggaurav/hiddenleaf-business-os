<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Communication Accounts Table
        if (! Schema::hasTable('comm_accounts')) {
            Schema::create('comm_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('provider', 50); // gmail, whatsapp, slack, facebook, instagram, linkedin, tiktok, twitter_x, internal
                $table->string('external_account_id', 150);
                $table->string('display_name', 150);
                $table->string('email', 150)->nullable();
                $table->string('phone_number', 50)->nullable();
                $table->boolean('enabled')->default(true);
                $table->enum('status', ['connected', 'syncing', 'healthy', 'reauth_required', 'error', 'disabled'])->default('connected');
                $table->text('encrypted_access_token')->nullable();
                $table->text('encrypted_refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->string('sync_cursor', 255)->nullable();
                $table->text('last_error')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['workspace_id', 'provider', 'external_account_id']);
                $table->index(['workspace_id', 'status']);
            });
        }

        // 2. Communication Conversations Table
        if (! Schema::hasTable('comm_conversations')) {
            Schema::create('comm_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('comm_accounts')->cascadeOnDelete();
                $table->string('provider', 50);
                $table->string('external_thread_id', 150);
                $table->string('subject', 255)->nullable();
                $table->string('participant_name', 150)->nullable();
                $table->string('participant_identifier', 150)->nullable(); // email or phone or social handle
                $table->text('last_message_preview')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->unsignedInteger('unread_count')->default(0);
                $table->enum('status', ['open', 'in_progress', 'resolved', 'closed', 'archived'])->default('open');
                $table->unsignedTinyInteger('priority_score')->default(50); // 0-100 from AttentionPriorityCalculator
                $table->string('sentiment', 20)->default('neutral');
                $table->string('intent', 30)->default('other');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->string('linked_entity_type', 50)->nullable(); // lead, deal, customer, vendor, task
                $table->unsignedBigInteger('linked_entity_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['account_id', 'external_thread_id']);
                $table->index(['workspace_id', 'provider', 'status'], 'comm_conv_ws_prov_stat_idx');
                $table->index(['workspace_id', 'priority_score']);
                $table->index(['workspace_id', 'unread_count']);
            });
        }

        // 3. Communication Messages Table
        if (! Schema::hasTable('comm_messages')) {
            Schema::create('comm_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('conversation_id')->constrained('comm_conversations')->cascadeOnDelete();
                $table->string('provider_message_id', 150);
                $table->string('idempotency_key', 100)->nullable()->unique();
                $table->enum('direction', ['inbound', 'outbound'])->default('inbound');
                $table->string('sender_name', 150)->nullable();
                $table->string('sender_identifier', 150)->nullable();
                $table->json('recipients')->nullable();
                $table->longText('body_text')->nullable();
                $table->longText('body_html')->nullable();
                $table->enum('delivery_status', ['draft', 'pending', 'sending', 'sent', 'delivered', 'read', 'failed'])->default('sent');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['conversation_id', 'sent_at']);
                $table->index(['workspace_id', 'direction', 'delivery_status'], 'comm_msg_ws_dir_stat_idx');
            });
        }

        // 4. Communication Participants Table
        if (! Schema::hasTable('comm_participants')) {
            Schema::create('comm_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->string('display_name', 150);
                $table->string('email', 150)->nullable();
                $table->string('phone_number', 50)->nullable();
                $table->string('provider', 50)->nullable();
                $table->string('provider_user_id', 150)->nullable();
                $table->string('avatar_url', 500)->nullable();
                $table->string('linked_entity_type', 50)->nullable();
                $table->unsignedBigInteger('linked_entity_id')->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'email']);
                $table->index(['workspace_id', 'phone_number']);
            });
        }

        // 5. Communication Attachments Table
        if (! Schema::hasTable('comm_attachments')) {
            Schema::create('comm_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
                $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
                $table->foreignId('message_id')->constrained('comm_messages')->cascadeOnDelete();
                $table->string('file_name', 255);
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('storage_disk', 50)->default('local');
                $table->string('file_path', 500);
                $table->string('external_attachment_id', 255)->nullable();
                $table->timestamps();

                $table->index(['workspace_id', 'message_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comm_attachments');
        Schema::dropIfExists('comm_participants');
        Schema::dropIfExists('comm_messages');
        Schema::dropIfExists('comm_conversations');
        Schema::dropIfExists('comm_accounts');
    }
};
