<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('sale_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('receivable_id')->nullable()->index();
            $table->unsignedBigInteger('settlement_id')->nullable()->index();
            $table->unsignedBigInteger('settlement_adjustment_id')->nullable()->index();

            $table->string('direction', 10)->index();
            $table->string('entry_type', 40)->index();
            $table->decimal('amount', 10, 2);
            $table->dateTime('occurred_at')->index();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->foreign('receivable_id')->references('id')->on('receivables')->nullOnDelete();
            $table->foreign('settlement_id')->references('id')->on('settlements')->nullOnDelete();
            $table->foreign('settlement_adjustment_id')->references('id')->on('settlement_adjustments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
