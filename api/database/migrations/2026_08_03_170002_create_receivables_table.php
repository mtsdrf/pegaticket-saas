<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('sale_id')->unique();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('event_id')->nullable()->index();
            $table->unsignedBigInteger('settlement_id')->nullable()->index();

            $table->string('status', 20)->default('scheduled')->index();
            $table->string('currency', 3)->default('BRL');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('platform_fee_amount', 10, 2)->default(0);
            $table->decimal('processor_fee_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->string('settlement_reference', 30)->default('event_end_d_plus_1');
            $table->dateTime('event_ends_at')->nullable()->index();
            $table->dateTime('available_at')->index();

            $table->string('provider', 40)->nullable()->index();
            $table->string('provider_charge_id')->nullable()->index();
            $table->string('provider_split_id')->nullable()->index();
            $table->json('metadata')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            $table->foreign('event_id')->references('id')->on('events')->nullOnDelete();
            $table->foreign('settlement_id')->references('id')->on('settlements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivables');
    }
};
