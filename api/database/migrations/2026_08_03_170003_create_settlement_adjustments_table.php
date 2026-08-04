<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settlement_adjustments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('settlement_id')->nullable()->index();
            $table->unsignedBigInteger('receivable_id')->nullable()->index();
            $table->unsignedBigInteger('sale_id')->nullable()->index();

            $table->string('type', 20)->index();
            $table->decimal('amount', 10, 2);
            $table->text('reason');
            $table->string('status', 20)->default('applied')->index();
            $table->json('metadata')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('settlement_id')->references('id')->on('settlements')->nullOnDelete();
            $table->foreign('receivable_id')->references('id')->on('receivables')->nullOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_adjustments');
    }
};
