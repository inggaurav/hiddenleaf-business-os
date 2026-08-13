<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('proposal_id')->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->date('issue_date')->nullable();
            $table->string('type')->default('proposal');
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->integer('status')->default(0); // 0: draft, 1: sent, 2: accepted, 3: rejected, 4: converted
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->unsignedBigInteger('workspace_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_proposal_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proposal_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_proposal_item_taxes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proposal_id');
            $table->unsignedBigInteger('item_id');
            $table->string('tax_name')->nullable();
            $table->decimal('tax_rate', 8, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_proposal_item_taxes');
        Schema::dropIfExists('sales_proposal_items');
        Schema::dropIfExists('sales_proposals');
    }
};
