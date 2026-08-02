<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_installments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->unsignedBigInteger('tenant_id')->index();

            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();

            $table->unsignedInteger('installment_number');
            $table->decimal('amount', 10, 2);
            $table->date('due_date');

            $table->boolean('is_paid')->default(false)->index();
            $table->timestamp('paid_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(['sale_id', 'installment_number'], 'uniq_order_installment_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_installments');
    }
};
