<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagamento de uma fatura (roadmap 1B). `provider` default 'manual' (o
 * ManualPaymentProvider desta onda). `idempotency_key` único garante que um
 * mesmo evento de webhook/tentativa não gere pagamento duplicado.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40)->default('manual');
            $table->string('provider_charge_id')->nullable()->index();
            $table->string('method', 20)->nullable(); // pix|card|...
            $table->decimal('amount', 10, 2);
            $table->string('status', 20)->index(); // pending|paid|failed|refunded
            $table->timestamp('paid_at')->nullable();
            $table->string('idempotency_key')->nullable()->unique();

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
