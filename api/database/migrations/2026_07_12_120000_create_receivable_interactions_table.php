<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receivable_interactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('sale_id')->constrained('sales');
            $table->foreignId('order_installment_id')->nullable()->constrained('sale_installments');
            $table->string('interaction_type', 30);
            $table->string('channel', 30)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('promised_amount', 12, 2)->nullable();
            $table->date('promised_due_date')->nullable();
            $table->dateTime('contacted_at');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'sale_id']);
            $table->index(['tenant_id', 'order_installment_id']);
            $table->index(['tenant_id', 'interaction_type']);
            $table->index(['tenant_id', 'contacted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_interactions');
    }
};
