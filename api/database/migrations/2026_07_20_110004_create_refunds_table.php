<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estorno de um pagamento (roadmap 1B) — usado tanto pelo arrependimento de
 * 7 dias quanto por estorno manual futuro. `protocol` único é o número de
 * protocolo exibido ao cliente. `requested_by` é o user id do solicitante
 * (nullable — pode ser gerado por processo automático).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();

            // Nullable: um arrependimento dentro dos 7 dias pode ocorrer
            // ainda em trial, antes de qualquer pagamento existir.
            $table->foreignId('payment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('type', 20); // total | partial
            $table->unsignedBigInteger('requested_by')->nullable()->index();
            $table->string('protocol')->unique();
            $table->string('provider_refund_id')->nullable();
            $table->string('status', 20)->index(); // requested|processing|done|failed

            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->unsignedBigInteger('deleted_by')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
